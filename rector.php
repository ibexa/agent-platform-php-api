<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

require_once __DIR__ . '/rector/AddReturnTypeFromPhpDocRector.php';

use Ibexa\AgentPlatform\Rector\AddReturnTypeFromPhpDocRector;
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;

return RectorConfig::configure()
    ->withRules([
        AddReturnTypeFromPhpDocRector::class,
    ])
    ->withPaths([
        __DIR__ . '/src/lib',
    ])
    ->withPhpSets(php83: true)
    ->withSets([
        SetList::TYPE_DECLARATION,
    ]);
