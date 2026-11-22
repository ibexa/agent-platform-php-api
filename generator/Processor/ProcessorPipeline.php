<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\AgentPlatform\Generator\Processor;

final class ProcessorPipeline implements SpecProcessor
{
    /** @param list<SpecProcessor> $processors */
    public function __construct(private readonly array $processors) {}

    public function process(\stdClass $spec): void
    {
        foreach ($this->processors as $processor) {
            $processor->process($spec);
        }
    }
}
