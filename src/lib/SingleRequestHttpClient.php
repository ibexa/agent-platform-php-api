<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\AgentPlatform;

use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\Response\ResponseStream;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * Prevents EventSourceHttpClient from replaying a streaming request.
 *
 * @internal
 */
final class SingleRequestHttpClient implements HttpClientInterface
{
    private bool $started = false;

    public function __construct(private readonly HttpClientInterface $client) {}

    public function request(
        string $method,
        string $url,
        array $options = []
    ): ResponseInterface {
        if ($this->started) {
            throw new TransportException('Automatic reconnection of event streams is disabled.');
        }
        $this->started = true;

        return $this->client->request($method, $url, $options);
    }

    public function stream(
        ResponseInterface | iterable $responses,
        ?float $timeout = null
    ): ResponseStreamInterface {
        return new ResponseStream((function () use ($responses, $timeout) {
            foreach ($this->client->stream($responses, $timeout) as $response => $chunk) {
                if ($chunk->getError() !== null || $chunk->isTimeout()) {
                    $chunk->getContent();
                }
                yield $response => $chunk;
            }
        })());
    }

    public function withOptions(array $options): static
    {
        if ($this->started) {
            throw new \LogicException('Options cannot be changed after the request starts.');
        }

        return new self($this->client->withOptions($options));
    }
}
