<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

$contents = file_get_contents(__DIR__ . '/openapi.json');
if ($contents === false) {
    throw new RuntimeException('Failed to read the OpenAPI specification.');
}

$spec = json_decode($contents, false, 512, JSON_THROW_ON_ERROR);
$pipeline = require __DIR__ . '/processors.php';
$pipeline->process($spec);

echo json_encode($spec, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
