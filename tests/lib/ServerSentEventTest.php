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
            "nt: update\ndata: first\ndata: second\nretry: 1500\n\n",
            "data: done\n\n",
        ], ['response_headers' => ['Content-Type' => 'text/event-stream; charset=utf-8']]);
        $http = new MockHttpClient($response);
        $config = (new Configuration())->setHost('https://example.test')->setAccessToken('token');

        $events = iterator_to_array((new ExecutionsApi($http, $config))->attachExecutionStreamEvents(8, 'previous'));

        self::assertCount(2, $events);
        self::assertInstanceOf(ServerSentEventSchema::class, $events[0]);
        self::assertSame('first' . "\n" . 'second', $events[0]->getData());
        self::assertSame('update', $events[0]->getEvent());
        self::assertSame('42', $events[0]->getId());
        self::assertSame(1500, $events[0]->getRetry());
        self::assertSame('message', $events[1]->getEvent());
        self::assertSame('done', $events[1]->getData());
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
            "data: one\n\n",
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
                self::assertSame('one', $event->getData());
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
            new MockResponse(["data: one\n\n", new TransportException('Disconnected')], [
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
        $http = new class(new MockResponse(["data: one\n\n", "data: two\n\n"], [
            'response_headers' => ['Content-Type' => 'text/event-stream'],
        ])) extends MockHttpClient {
            public ?ResponseInterface $response = null;

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
        self::assertSame('one', $events->current()->getData());
        unset($events);

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
            new MockResponse(["data: one\n\n", ''], [
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
        self::assertTrue(method_exists(PublicApi::class, 'attachExecutionStreamEvents'));
        self::assertTrue(method_exists(PublicApi::class, 'attachMessageStreamEvents'));
        self::assertTrue(method_exists(PublicApi::class, 'sendMessageStreamEvents'));
    }
}
