<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

use Ibexa\AgentPlatform\Generator\Processor\EventStreamProcessor;
use Ibexa\AgentPlatform\Generator\Processor\ExecutionResultProcessor;
use Ibexa\AgentPlatform\Generator\Processor\ParameterExampleProcessor;
use Ibexa\AgentPlatform\Generator\Processor\ProcessorPipeline;

require_once __DIR__ . '/Processor/SpecProcessor.php';
require_once __DIR__ . '/Processor/ProcessorPipeline.php';
require_once __DIR__ . '/Processor/ExecutionResultProcessor.php';
require_once __DIR__ . '/Processor/ParameterExampleProcessor.php';
require_once __DIR__ . '/Processor/EventStreamProcessor.php';

return new ProcessorPipeline([
    new ExecutionResultProcessor(),
    new ParameterExampleProcessor(),
    new EventStreamProcessor(),
]);
