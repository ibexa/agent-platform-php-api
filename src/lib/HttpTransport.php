<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\AgentPlatform;

use GuzzleHttp\Psr7\MultipartStream;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface as SymfonyResponseInterface;

final class HttpTransport
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly Configuration $config,
    ) {}

    /**
     * @template T
     *
     * @param callable(ResponseInterface): T $process
     *
     * @return AsyncResult<T>
     */
    public function send(
        RequestInterface $request,
        callable $process
    ): AsyncResult {
        $response = $this->start($request);

        return new AsyncResult(function () use ($request, $response, $process) {
            try {
                $psrResponse = new Response(
                    $response->getStatusCode(),
                    $response->getHeaders(false),
                    $response->getContent(false),
                );

                return $process($psrResponse);
            } catch (TransportExceptionInterface $exception) {
                throw $this->transportException($exception);
            } finally {
                $this->writeDebug($response);
            }
        });
    }

    /**
     * Starts an unbuffered response; chunks can be consumed with the Symfony client.
     */
    public function start(
        RequestInterface $request,
        bool $buffer = true,
        bool $followRedirects = true
    ): SymfonyResponseInterface {
        $body = $request->getBody();
        $headers = $request->getHeaders();
        if ($body instanceof MultipartStream && !$request->hasHeader('Content-Type')) {
            $headers['Content-Type'] = ['multipart/form-data; boundary=' . $body->getBoundary()];
        }
        $options = [
            'headers' => $headers,
            'buffer' => $buffer,
        ];
        if (!$followRedirects) {
            $options['max_redirects'] = 0;
        }
        if ($body->getSize() !== 0) {
            $options['body'] = static fn (int $length): string => $body->read($length);
            if ($body->getSize() !== null && !$request->hasHeader('Content-Length')) {
                $options['headers']['Content-Length'] = [(string) $body->getSize()];
            }
        }
        if ($this->config->getCertFile()) {
            $options['local_cert'] = $this->config->getCertFile();
        }
        if ($this->config->getKeyFile()) {
            $options['local_pk'] = $this->config->getKeyFile();
        }

        try {
            return $this->client->request($request->getMethod(), (string) $request->getUri(), $options);
        } catch (TransportExceptionInterface $exception) {
            throw $this->transportException($exception);
        }
    }

    public function writeDebug(SymfonyResponseInterface $response): void
    {
        if ($this->config->getDebug()) {
            $path = $this->config->getDebugFile();
            if (file_put_contents($path, (string) $response->getInfo('debug'), FILE_APPEND) === false) {
                throw new \RuntimeException('Failed to write the debug file: ' . $path);
            }
        }
    }

    public function transportException(TransportExceptionInterface $exception): ApiException
    {
        return new ApiException(
            "[{$exception->getCode()}] {$exception->getMessage()}",
            (int) $exception->getCode(),
            null,
            null
        );
    }
}
