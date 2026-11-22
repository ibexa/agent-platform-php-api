<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\AgentPlatform;

use Ibexa\AgentPlatform\ApiException;
use Ibexa\AgentPlatform\Configuration;
use Ibexa\AgentPlatform\ObjectSerializer;
use PHPUnit\Framework\TestCase;

final class SupportingTypesTest extends TestCase
{
    /**
     * @dataProvider supportingClassProvider
     */
    public function testPublicSignaturesHaveNativeTypes(string $class): void
    {
        $reflection = new \ReflectionClass($class);
        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $class) {
                continue;
            }

            if (!$method->isConstructor()) {
                self::assertTrue($method->hasReturnType(), $class . '::' . $method->getName());
            }
            foreach ($method->getParameters() as $parameter) {
                self::assertTrue($parameter->hasType(), $class . '::' . $method->getName() . '($' . $parameter->getName() . ')');
            }
        }
    }

    /** @return iterable<array{class-string}> */
    public static function supportingClassProvider(): iterable
    {
        yield [ApiException::class];
        yield [Configuration::class];
        yield [ObjectSerializer::class];
    }

    public function testConfigurationNullableDefaultsAndFluentSetters(): void
    {
        $config = new Configuration();
        self::assertNull($config->getCertFile());
        self::assertNull($config->getKeyFile());
        self::assertNull($config->getApiKeyWithPrefix('token'));
        self::assertSame($config, $config->setApiKey('token', 'secret')->setApiKeyPrefix('token', 'Bearer'));
        self::assertSame('Bearer secret', $config->getApiKeyWithPrefix('token'));
        self::assertSame($config, $config->setCertFile('/tmp/cert')->setKeyFile('/tmp/key'));
        self::assertSame($config, $config->setCertFile(null)->setKeyFile(null));
        self::assertNull($config->getCertFile());
        self::assertNull($config->getKeyFile());
    }

    public function testExceptionKeepsMixedResponseValues(): void
    {
        $exception = new ApiException('Error', 422, null, ['errors' => ['invalid']]);
        self::assertNull($exception->getResponseHeaders());
        self::assertSame(['errors' => ['invalid']], $exception->getResponseBody());
        self::assertNull($exception->getResponseObject());
        $exception->setResponseObject(42);
        self::assertSame(42, $exception->getResponseObject());
    }

    public function testSerializerKeepsMixedValuesAndQueryEncoding(): void
    {
        self::assertSame(42, ObjectSerializer::sanitizeTimestamp(42));
        self::assertSame('2025-01-01T12:34:56.123456Z', ObjectSerializer::sanitizeTimestamp('2025-01-01T12:34:56.123456789Z'));
        self::assertSame(42, ObjectSerializer::deserialize('42', 'int'));
        self::assertSame('a=b c', ObjectSerializer::buildQuery(['a' => 'b c'], false));
        self::assertSame('1|2', ObjectSerializer::serializeCollection([1, 2], 'pipes'));
    }
}
