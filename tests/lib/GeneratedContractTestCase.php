<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\AgentPlatform;

use PHPUnit\Framework\TestCase;

abstract class GeneratedContractTestCase extends TestCase
{
    protected function responseBody(string $type): string
    {
        if (str_ends_with($type, '[]') || str_starts_with($type, 'array<') || $type === 'array') {
            return '[]';
        }
        if (class_exists($type) && method_exists($type, 'getAllowableEnumValues')) {
            return json_encode($type::getAllowableEnumValues()[0], JSON_THROW_ON_ERROR);
        }

        return match ($type) {
            'string', '\SplFileObject' => 'example',
            'int', 'integer' => '1',
            'float', 'double', 'number' => '1.5',
            'bool', 'boolean' => 'true',
            default => '{}',
        };
    }

    protected function assertResponseType(
        string $type,
        mixed $value
    ): void {
        if ($type === '') {
            self::assertNull($value);
        } elseif (str_ends_with($type, '[]') || str_starts_with($type, 'array<') || in_array($type, ['array', 'object'], true)) {
            self::assertIsArray($value);
        } elseif (class_exists($type) && method_exists($type, 'getAllowableEnumValues')) {
            self::assertContains($value, $type::getAllowableEnumValues());
        } elseif (class_exists($type)) {
            self::assertInstanceOf($type, $value);
        } else {
            match ($type) {
                'string' => self::assertIsString($value),
                'int', 'integer' => self::assertIsInt($value),
                'float', 'double', 'number' => self::assertIsFloat($value),
                'bool', 'boolean' => self::assertIsBool($value),
                'mixed' => self::assertNotNull($value),
                default => self::fail('Unsupported response type: ' . $type),
            };
        }
    }
}
