<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\AgentPlatform;

use Ibexa\AgentPlatform\Client\ExecutionsApi;
use Ibexa\AgentPlatform\HeaderSelector;
use PHPUnit\Framework\TestCase;

final class HeaderSelectorTest extends TestCase
{
    public function testStreamingRequestCanBeCreated(): void
    {
        $request = (new ExecutionsApi())->attachExecutionStreamRequest(42);

        self::assertSame('/api/v1/executions/42/stream', $request->getUri()->getPath());
        self::assertSame('application/json,text/event-stream;q=0.9', $request->getHeaderLine('Accept'));
    }

    /**
     * @dataProvider headersProvider
     *
     * @param string[] $accept
     */
    public function testSelectHeaders(
        array $accept,
        string $expected
    ): void {
        $headers = (new HeaderSelector())->selectHeaders($accept, '', false);

        self::assertSame($expected, $headers['Accept']);
        self::assertSame('application/json', $headers['Content-Type']);
    }

    /** @return iterable<string, array{string[], string}> */
    public static function headersProvider(): iterable
    {
        yield 'single' => [['text/event-stream'], 'text/event-stream'];
        yield 'without json' => [['text/plain', 'text/html'], 'text/plain,text/html'];
        yield 'json and stream' => [
            ['text/event-stream', 'application/json'],
            'application/json,text/event-stream;q=0.9',
        ];
        yield 'existing weights and json types' => [
            ['text/plain;q=0.2', 'application/problem+json;q=0.8', 'application/json;q=0.5', 'text/html;q=0.8'],
            'application/json,application/problem+json;q=0.9,text/html;q=0.8,text/plain;q=0.7',
        ];
        yield 'more than 28 headers' => [
            array_merge(['application/json'], array_map(static fn (int $index): string => 'text/type' . $index . ';q=0.' . str_pad((string) (999 - $index), 3, '0', STR_PAD_LEFT), range(0, 27))),
            'application/json,' . implode(',', array_map(static fn (int $index): string => 'text/type' . $index . ';q=0.' . rtrim((string) (999 - $index), '0'), range(0, 27))),
        ];
    }

    public function testNextWeightSequence(): void
    {
        $selector = new HeaderSelector();
        $weights = [1000, 900, 800, 700, 600, 500, 400, 300, 200, 100, 90, 80, 70, 60, 50, 40, 30, 20, 10, 9, 8, 7, 6, 5, 4, 3, 2, 1];

        foreach (array_slice($weights, 1) as $index => $expected) {
            self::assertSame($expected, $selector->getNextWeight($weights[$index], false));
        }

        self::assertSame(1, $selector->getNextWeight(1, false));
        self::assertSame(999, $selector->getNextWeight(1000, true));
        self::assertSame(1, $selector->getNextWeight(1, true));
    }
}
