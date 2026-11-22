<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\AgentPlatform\Generator\Processor;

final class ParameterExampleProcessor implements SpecProcessor
{
    public function process(\stdClass $spec): void
    {
        foreach ($spec->paths as $methods) {
            foreach ($methods as $operation) {
                if (!$operation instanceof \stdClass || !isset($operation->responses)) {
                    continue;
                }

                foreach ($operation->parameters ?? [] as $parameter) {
                    $schema = $parameter->schema ?? null;
                    if (isset($schema->{'$ref'})) {
                        $name = basename($schema->{'$ref'});
                        $schema = $spec->components->schemas->$name;
                    }
                    if (isset($schema->enum)) {
                        $parameter->{'x-contract-example'} = var_export($schema->enum[0], true);
                    }
                }
            }
        }
    }
}
