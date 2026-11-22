<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\AgentPlatform;

use Ibexa\AgentPlatform\ApiException;
use Ibexa\AgentPlatform\Client\ExecutionsApi;
use Ibexa\AgentPlatform\Client\MessagesApi;
use Ibexa\AgentPlatform\Client\PublicApi;
use Ibexa\AgentPlatform\Configuration;
use Ibexa\AgentPlatform\Model\SendMessagePayloadSchema;
use Ibexa\AgentPlatform\Model\ServerSentEventSchema;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ServerSentEventTest extends TestCase
{
    public function testGeneratedMethodParsesFragmentedEvents(): void
    {
        $response = new MockResponse([
            ": heartbeat\n\nid: 42\neve",
            "nt: update\ndata: {\"delta\": \"first\",\ndata: \"agent_id\": 1}\nretry: 1500\n\n",
            "data: {\"status\":\"SUCCESS\"}\n\n",
        ], ['response_headers' => ['Content-Type' => 'text/event-stream; charset=utf-8']]);
        $http = new MockHttpClient($response);
        $config = (new Configuration())->setHost('https://example.test')->setAccessToken('token');

        $events = iterator_to_array((new ExecutionsApi($http, $config))->attachExecutionStreamEvents(8, 'previous'));

        self::assertCount(2, $events);
        self::assertInstanceOf(ServerSentEventSchema::class, $events[0]);
        self::assertSame(['delta' => 'first', 'agent_id' => 1], $events[0]->getData());
        self::assertSame('update', $events[0]->getEvent());
        self::assertSame('42', $events[0]->getId());
        self::assertSame(1500, $events[0]->getRetry());
        self::assertSame('message', $events[1]->getEvent());
        self::assertSame(['status' => 'SUCCESS'], $events[1]->getData());
        self::assertContains('Accept: text/event-stream', $response->getRequestOptions()['headers']);
        self::assertContains('Last-Event-ID: previous', $response->getRequestOptions()['headers']);
        self::assertContains('Authorization: Bearer token', $response->getRequestOptions()['headers']);
        self::assertFalse($response->getRequestOptions()['buffer']);
        self::assertSame(0, $response->getRequestOptions()['max_redirects']);
        self::assertSame('https://example.test/api/v1/executions/8/stream', $response->getRequestUrl());
    }

    public function testPostEventMethodPreservesBodyAndDoesNotReplayOnFailure(): void
    {
        $body = '';
        $response = new MockResponse([
            "data: {\"delta\":\"one\"}\n\n",
            new TransportException('Stream failed'),
        ], ['response_headers' => ['Content-Type' => 'text/event-stream']]);
        $http = new MockHttpClient(static function (
            $method,
            $url,
            $options
        ) use (&$body, $response) {
            while (($part = $options['body'](8192)) !== '') {
                $body .= $part;
            }

            return $response;
        });
        $config = (new Configuration())->setHost('https://example.test')->setAccessToken('token');
        $api = new MessagesApi($http, $config);
        $events = $api->sendMessageStreamEvents(5, new SendMessagePayloadSchema(['content' => 'hello']));

        try {
            foreach ($events as $event) {
                self::assertSame(['delta' => 'one'], $event->getData());
            }
            self::fail('Expected an API exception.');
        } catch (ApiException $exception) {
            self::assertStringContainsString('Stream failed', $exception->getMessage());
        }

        self::assertSame(1, $http->getRequestsCount());
        self::assertSame('POST', $response->getRequestMethod());
        self::assertSame('hello', json_decode($body, true, 512, JSON_THROW_ON_ERROR)['content']);
        self::assertContains('Authorization: Bearer token', $response->getRequestOptions()['headers']);
    }

    public function testGetEventStreamDoesNotReconnectOnFailure(): void
    {
        $http = new MockHttpClient([
            new MockResponse(["data: {\"delta\":\"one\"}\n\n", new TransportException('Disconnected')], [
                'response_headers' => ['Content-Type' => 'text/event-stream'],
            ]),
            new MockResponse(),
        ]);
        try {
            iterator_to_array((new ExecutionsApi($http))->attachExecutionStreamEvents(1));
            self::fail('Expected an API exception.');
        } catch (ApiException $exception) {
            self::assertStringContainsString('Disconnected', $exception->getMessage());
        }
        self::assertSame(1, $http->getRequestsCount());
    }

    public function testHttpErrorIncludesBody(): void
    {
        $http = new MockHttpClient(new MockResponse('denied', [
            'http_code' => 403,
            'response_headers' => ['Content-Type' => 'application/json'],
        ]));
        try {
            iterator_to_array((new ExecutionsApi($http))->attachExecutionStreamEvents(1));
            self::fail('Expected an API exception.');
        } catch (ApiException $exception) {
            self::assertSame(403, $exception->getCode());
            self::assertSame('denied', $exception->getResponseBody());
        }
    }

    public function testRedirectIsReportedWithoutReplayingRequest(): void
    {
        $http = new MockHttpClient(new MockResponse('', [
            'http_code' => 307,
            'response_headers' => ['Location' => 'https://other.example/stream'],
        ]));
        try {
            iterator_to_array((new ExecutionsApi($http))->attachExecutionStreamEvents(1));
            self::fail('Expected an API exception.');
        } catch (ApiException $exception) {
            self::assertSame(307, $exception->getCode());
        }
        self::assertSame(1, $http->getRequestsCount());
    }

    public function testReleasingIteratorCancelsResponse(): void
    {
        $http = new class(new MockResponse(["data: {\"delta\":\"one\"}\n\n", "data: {\"delta\":\"two\"}\n\n"], [
            'response_headers' => ['Content-Type' => 'text/event-stream'],
        ])) extends MockHttpClient {
            public ?ResponseInterface $response = null;

            /** @param array<string, mixed> $options */
            public function request(
                string $method,
                string $url,
                array $options = []
            ): ResponseInterface {
                return $this->response = parent::request($method, $url, $options);
            }
        };
        $events = (new ExecutionsApi($http))->attachExecutionStreamEvents(1);
        $events->rewind();
        self::assertSame(['delta' => 'one'], $events->current()->getData());
        unset($events);

        self::assertInstanceOf(ResponseInterface::class, $http->response);
        self::assertTrue($http->response->getInfo('canceled'));
    }

    public function testSuccessfulResponseRequiresEventStreamContentType(): void
    {
        $http = new MockHttpClient(new MockResponse('{}', [
            'response_headers' => ['Content-Type' => 'application/json'],
        ]));

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('text/event-stream');
        iterator_to_array((new ExecutionsApi($http))->attachExecutionStreamEvents(1));
    }

    public function testNon200SuccessAlsoRequiresEventStreamContentType(): void
    {
        $http = new MockHttpClient(new MockResponse('{}', [
            'http_code' => 201,
            'response_headers' => ['Content-Type' => 'application/json'],
        ]));

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('text/event-stream');
        iterator_to_array((new ExecutionsApi($http))->attachExecutionStreamEvents(1));
    }

    public function testTimeoutDoesNotReconnect(): void
    {
        $http = new MockHttpClient([
            new MockResponse(["data: {\"delta\":\"one\"}\n\n", ''], [
                'response_headers' => ['Content-Type' => 'text/event-stream'],
            ]),
            new MockResponse(),
        ]);
        try {
            iterator_to_array((new ExecutionsApi($http))->attachExecutionStreamEvents(1));
            self::fail('Expected an API exception.');
        } catch (ApiException $exception) {
            self::assertStringContainsString('timeout', strtolower($exception->getMessage()));
        }
        self::assertSame(1, $http->getRequestsCount());
    }

    public function testPublicApiAlsoGeneratesEventMethods(): void
    {
        $api = new \ReflectionClass(PublicApi::class);
        foreach (['attachExecutionStreamEvents', 'attachMessageStreamEvents', 'sendMessageStreamEvents'] as $method) {
            self::assertTrue($api->hasMethod($method));
        }
    }

    /**
     * @dataProvider payloadProvider
     *
     * @param array<mixed> $expected
     */
    public function testEventDataIsDecodedToArray(
        string $json,
        array $expected
    ): void {
        $http = new MockHttpClient(new MockResponse("data: $json\n\n", [
            'response_headers' => ['Content-Type' => 'text/event-stream'],
        ]));

        $events = iterator_to_array((new ExecutionsApi($http))->attachExecutionStreamEvents(1));

        self::assertSame($expected, $events[0]->getData());
    }

    /** @return iterable<string, array{string, array<mixed>}> */
    public function payloadProvider(): iterable
    {
        yield 'nested objects' => ['{"message":{"content":"Hello","tool_calls":null},"agent_id":1}', [
            'message' => ['content' => 'Hello', 'tool_calls' => null], 'agent_id' => 1,
        ]];
        yield 'list' => ['[{"delta":"Hello"},{"delta":"world"}]', [['delta' => 'Hello'], ['delta' => 'world']]];
        yield 'empty object' => ['{}', []];
        yield 'empty list' => ['[]', []];
    }

    /**
     * @dataProvider invalidPayloadProvider
     */
    public function testInvalidEventDataIsReported(string $payload): void
    {
        $http = new MockHttpClient(new MockResponse("data: $payload\n\n", [
            'response_headers' => ['Content-Type' => 'text/event-stream'],
        ]));

        try {
            iterator_to_array((new ExecutionsApi($http))->attachExecutionStreamEvents(1));
            self::fail('Expected an API exception.');
        } catch (ApiException $exception) {
            self::assertStringContainsString('Error JSON decoding event data', $exception->getMessage());
            self::assertSame($payload, $exception->getResponseBody());
            self::assertSame(200, $exception->getCode());
        }
        self::assertSame(1, $http->getRequestsCount());
    }

    /** @return iterable<string, array{string}> */
    public function invalidPayloadProvider(): iterable
    {
        yield 'malformed JSON' => ['{invalid'];
        yield 'string' => ['"hello"'];
        yield 'number' => ['42'];
        yield 'null' => ['null'];
        yield 'empty data' => [''];
    }
}
