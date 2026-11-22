<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\AgentPlatform\Generator\Processor;

final class EventStreamProcessor implements SpecProcessor
{
    public function process(\stdClass $spec): void
    {
        $eventSchema = null;
        foreach ($spec->paths as $methods) {
            foreach ($methods as $operation) {
                if (!$operation instanceof \stdClass || !isset($operation->responses)) {
                    continue;
                }

                foreach ($operation->responses as $response) {
                    $itemSchema = $response->content->{'text/event-stream'}->itemSchema ?? null;
                    if ($itemSchema === null) {
                        continue;
                    }
                    if (!$itemSchema instanceof \stdClass || ($eventSchema !== null && $eventSchema != $itemSchema)) {
                        throw new \RuntimeException('Streaming endpoints have incompatible event schemas.');
                    }
                    $eventSchema = $itemSchema;
                    $operation->{'x-sse-events'} = true;
                }
            }
        }

        if ($eventSchema === null || isset($spec->components->schemas->ServerSentEventSchema)) {
            throw new \RuntimeException('Cannot generate the server-sent event model.');
        }

        $eventSchema = clone $eventSchema;
        if (!isset($eventSchema->properties) || !$eventSchema->properties instanceof \stdClass) {
            throw new \RuntimeException('Invalid server-sent event properties.');
        }
        $eventSchema->properties = clone $eventSchema->properties;
        $eventSchema->properties->data = (object) [
            'type' => 'object',
            'additionalProperties' => new \stdClass(),
            'description' => 'The JSON event payload decoded as an associative array.',
        ];
        $spec->components->schemas->ServerSentEventSchema = $eventSchema;
    }
}
