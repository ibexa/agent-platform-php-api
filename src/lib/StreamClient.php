<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\AgentPlatform;

use Ibexa\AgentPlatform\Model\ServerSentEventSchema;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\HttpClient\Chunk\ServerSentEvent;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class StreamClient
{
    private readonly HttpClientInterface $client;

    private readonly HttpTransport $transport;

    private readonly Configuration $config;

    public function __construct(
        ?HttpClientInterface $client = null,
        ?Configuration $config = null
    ) {
        $this->client = $client ?? HttpClient::create();
        $this->config = $config ?? Configuration::getDefaultConfiguration();
        $this->transport = new HttpTransport($this->client, $this->config);
    }

    /**
     * @return \Generator<int, ServerSentEventSchema>
     */
    public function events(RequestInterface $request): \Generator
    {
        $request = $request->withHeader('Accept', 'text/event-stream');
        $client = new EventSourceHttpClient(new SingleRequestHttpClient($this->client));
        $transport = new HttpTransport($client, $this->config);
        $response = $transport->start($request, false, false);

        try {
            $status = $response->getStatusCode();
            if ($status < 200 || $status > 299) {
                throw new ApiException(
                    sprintf('[%d] Error connecting to the API (%s)', $status, (string) $request->getUri()),
                    $status,
                    $response->getHeaders(false),
                    $response->getContent(false),
                );
            }
            $headers = $response->getHeaders(false);
            if (!preg_match('/^text\/event-stream(?:;|$)/i', $headers['content-type'][0] ?? '')) {
                throw new ApiException(
                    sprintf('Expected text/event-stream response (%s)', (string) $request->getUri()),
                    $status,
                    $headers,
                    null,
                );
            }

            foreach ($client->stream($response) as $chunk) {
                if ($chunk instanceof ServerSentEvent) {
                    try {
                        $data = $chunk->getArrayData();
                    } catch (DecodingExceptionInterface $exception) {
                        throw new ApiException(
                            sprintf('Error JSON decoding event data (%s): %s', (string) $request->getUri(), $exception->getMessage()),
                            $response->getStatusCode(),
                            $response->getHeaders(false),
                            $chunk->getData(),
                        );
                    }
                    yield new ServerSentEventSchema([
                        'data' => $data,
                        'event' => $chunk->getType(),
                        'id' => $chunk->getId(),
                        'retry' => (int) round($chunk->getRetry() * 1000),
                    ]);
                }
            }
        } catch (DecodingExceptionInterface $exception) {
            throw new ApiException(
                sprintf('Error JSON decoding event data (%s): %s', (string) $request->getUri(), $exception->getMessage()),
                $response->getStatusCode(),
                $response->getHeaders(false),
                null,
            );
        } catch (TransportExceptionInterface $exception) {
            throw $transport->transportException($exception);
        } finally {
            $response->cancel();
            $transport->writeDebug($response);
        }
    }

    /**
     * Yields raw response body chunks, not parsed server-sent events.
     *
     * @return \Generator<int, string>
     */
    public function stream(RequestInterface $request): \Generator
    {
        $request = $request->withHeader('Accept', 'text/event-stream');
        $response = $this->transport->start($request, false);

        try {
            $status = $response->getStatusCode();
            if ($status < 200 || $status > 299) {
                throw new ApiException(
                    sprintf('[%d] Error connecting to the API (%s)', $status, (string) $request->getUri()),
                    $status,
                    $response->getHeaders(false),
                    $response->getContent(false),
                );
            }
            foreach ($this->client->stream($response) as $chunk) {
                if ($chunk->isTimeout()) {
                    throw new \RuntimeException('Timed out while reading the response stream.');
                }
                $content = $chunk->getContent();
                if ($content !== '') {
                    yield $content;
                }
            }
        } catch (TransportExceptionInterface $exception) {
            throw $this->transport->transportException($exception);
        } finally {
            $response->cancel();
            $this->transport->writeDebug($response);
        }
    }
}
