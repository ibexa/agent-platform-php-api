<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\AgentPlatform;

use Ibexa\AgentPlatform\ApiException;
use Ibexa\AgentPlatform\Client\AgentsApi;
use Ibexa\AgentPlatform\Client\ExecutionsApi;
use Ibexa\AgentPlatform\Client\FilesApi;
use Ibexa\AgentPlatform\Client\LoginApi;
use Ibexa\AgentPlatform\Configuration;
use Ibexa\AgentPlatform\StreamClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class SymfonyHttpClientTest extends TestCase
{
    public function testAsyncRequestsStartImmediatelyAndWaitIsRepeatable(): void
    {
        $http = new MockHttpClient([
            new MockResponse('{"agents":[],"total_count":1}'),
            new MockResponse('{"agents":[],"total_count":2}'),
        ]);
        $api = new AgentsApi($http);

        $first = $api->listAgentsAsync();
        $second = $api->listAgentsAsyncWithHttpInfo();

        self::assertSame(2, $http->getRequestsCount());
        self::assertSame(1, $first->wait()->getTotalCount());
        self::assertSame($first->wait(), $first->wait());
        [$model, $status, $headers] = $second->wait();
        self::assertSame(2, $model->getTotalCount());
        self::assertSame(200, $status);
        self::assertIsArray($headers);
    }

    public function testAsyncHttpErrorContainsResponse(): void
    {
        $api = new AgentsApi(new MockHttpClient(new MockResponse('{"error":"bad"}', [
            'http_code' => 422,
            'response_headers' => ['Content-Type' => 'application/json'],
        ])));
        $result = $api->listAgentsAsync();

        for ($i = 0; $i < 2; ++$i) {
            try {
                $result->wait();
                self::fail('Expected an API exception.');
            } catch (ApiException $exception) {
                self::assertSame(422, $exception->getCode());
                self::assertSame('{"error":"bad"}', $exception->getResponseBody());
                $headers = $exception->getResponseHeaders();
                self::assertIsArray($headers);
                self::assertArrayHasKey('content-type', $headers);
                self::assertSame(['application/json'], $headers['content-type']);
            }
        }
    }

    public function testTransportFailureIsReported(): void
    {
        $api = new AgentsApi(new MockHttpClient(new MockResponse('', ['error' => 'Connection failed'])));

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Connection failed');
        $api->listAgents();
    }

    public function testInvalidJsonIsReported(): void
    {
        $api = new AgentsApi(new MockHttpClient(new MockResponse('not JSON')));

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Error JSON decoding');
        $api->listAgents();
    }

    public function testVoidOperationReportsHttpFailure(): void
    {
        $api = new LoginApi(new MockHttpClient(new MockResponse('denied', ['http_code' => 403])));

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(403);
        $api->logoutAll();
    }

    public function testFormAndTlsOptions(): void
    {
        $options = [];
        $body = '';
        $http = new MockHttpClient(static function (
            $method,
            $url,
            $requestOptions
        ) use (&$options, &$body) {
            $options = $requestOptions;
            while (($part = $requestOptions['body'](8192)) !== '') {
                $body .= $part;
            }

            return new MockResponse('{"access_token":"example","token_type":"bearer"}');
        });
        $config = (new Configuration())->setCertFile('/example/cert.pem')->setKeyFile('/example/key.pem');

        (new LoginApi($http, $config))->login('alice', 'secret');

        self::assertStringContainsString('username=alice', $body);
        self::assertStringContainsString('password=secret', $body);
        self::assertSame('/example/cert.pem', $options['local_cert']);
        self::assertSame('/example/key.pem', $options['local_pk']);
    }

    public function testDefaultDebugOutputSupportsStreamWrappers(): void
    {
        $config = (new Configuration())->setDebug(true);
        $api = new AgentsApi(new MockHttpClient(new MockResponse('{"agents":[],"total_count":0}')), $config);

        ob_start();
        try {
            $api->listAgents();
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        self::assertIsString($output);
    }

    public function testDebugWriteFailureIsReported(): void
    {
        $config = (new Configuration())->setDebug(true)->setDebugFile('/nonexistent/agent-platform-debug.log');
        $api = new AgentsApi(new MockHttpClient(new MockResponse('{"agents":[],"total_count":0}')), $config);

        set_error_handler(static fn () => true);
        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Failed to write the debug file');
            $api->listAgents();
        } finally {
            restore_error_handler();
        }
    }

    public function testMultipartIncludesBoundaryAndFile(): void
    {
        $headers = [];
        $body = '';
        $http = new MockHttpClient(static function (
            $method,
            $url,
            $options
        ) use (&$headers, &$body) {
            $headers = $options['headers'];
            while (($part = $options['body'](8192)) !== '') {
                $body .= $part;
            }

            return new MockResponse('{}');
        });
        $file = new \SplFileObject(__FILE__, 'r');
        (new FilesApi($http))->uploadFile(1, $file);

        $contentType = implode("\n", $headers);
        self::assertMatchesRegularExpression('/Content-Type: multipart\/form-data; boundary=([^\\s]+)/i', $contentType);
        if (preg_match('/boundary=([^\\s]+)/i', $contentType, $matches) !== 1) {
            self::fail('Missing multipart boundary.');
        }
        self::assertStringContainsString('--' . $matches[1], $body);
        self::assertStringContainsString('name="file"', $body);
        self::assertStringContainsString('final class SymfonyHttpClientTest', $body);
    }

    public function testStreamingYieldsChunksAndSetsAccept(): void
    {
        $mock = new MockResponse(["data: one\n", "\ndata: two\n\n"]);
        $http = new MockHttpClient($mock);
        $config = (new Configuration())->setHost('https://example.test')->setAccessToken('token');
        $request = (new ExecutionsApi($http, $config))->attachExecutionStreamRequest(42);

        $chunks = iterator_to_array((new StreamClient($http, $config))->stream($request));

        self::assertSame(["data: one\n", "\ndata: two\n\n"], $chunks);
        self::assertContains('Accept: text/event-stream', $mock->getRequestOptions()['headers']);
        self::assertContains('Authorization: Bearer token', $mock->getRequestOptions()['headers']);
        self::assertFalse($mock->getRequestOptions()['buffer']);
    }

    public function testStreamingHttpErrorPreservesBody(): void
    {
        $http = new MockHttpClient(new MockResponse('denied', ['http_code' => 403]));
        $request = (new ExecutionsApi($http))->attachExecutionStreamRequest(42);

        try {
            iterator_to_array((new StreamClient($http))->stream($request));
            self::fail('Expected an API exception.');
        } catch (ApiException $exception) {
            self::assertSame(403, $exception->getCode());
            self::assertSame('denied', $exception->getResponseBody());
        }
    }

    public function testStreamingNetworkFailureIsReported(): void
    {
        $http = new MockHttpClient(new MockResponse(["data: one\n\n", new TransportException('Stream failed')]));
        $request = (new ExecutionsApi($http))->attachExecutionStreamRequest(42);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Stream failed');
        iterator_to_array((new StreamClient($http))->stream($request));
    }
}
