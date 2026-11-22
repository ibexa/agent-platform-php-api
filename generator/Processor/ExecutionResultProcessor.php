<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\AgentPlatform\Generator\Processor;

final class ExecutionResultProcessor implements SpecProcessor
{
    public function process(\stdClass $spec): void
    {
        $resultSchema = $spec->components->schemas->ExecutionResponseSchema->properties->result;
        if (($resultSchema->anyOf ?? null) != [(object) [], (object) ['type' => 'null']]) {
            throw new \RuntimeException('Unexpected execution result schema.');
        }
        $resultSchema->{'x-unconstrained-value'} = true;
    }
}
