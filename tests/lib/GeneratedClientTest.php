<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\AgentPlatform;

use Ibexa\AgentPlatform\ApiException;
use Ibexa\AgentPlatform\Client\AgentsApi;
use Ibexa\AgentPlatform\Configuration;
use Ibexa\AgentPlatform\Model\AdminModelPricingResponseSchema;
use Ibexa\AgentPlatform\Model\AdminModelProfileResponseSchema;
use Ibexa\AgentPlatform\Model\AgentListItemResponseSchema;
use Ibexa\AgentPlatform\Model\AgentListResponseSchema;
use Ibexa\AgentPlatform\Model\ConversationFeedbackPayloadSchema;
use Ibexa\AgentPlatform\Model\ExecutionResponseSchema;
use Ibexa\AgentPlatform\Model\ExecutionStatus;
use Ibexa\AgentPlatform\Model\ModelInterface;
use Ibexa\AgentPlatform\Model\ModelReference;
use Ibexa\AgentPlatform\Model\ModelShortlistResponseSchema;
use Ibexa\AgentPlatform\Model\UpdateAgentPayloadSchema;
use Ibexa\AgentPlatform\ObjectSerializer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GeneratedClientTest extends TestCase
{
    public function testGeneratedModelClassesLoad(): void
    {
        foreach (glob(__DIR__ . '/../../src/lib/Model/*.php') as $file) {
            $class = 'Ibexa\\AgentPlatform\\Model\\' . basename($file, '.php');
            self::assertTrue(class_exists($class) || interface_exists($class), $class);
        }
    }

    public function testScalarAccessorsAndModelMetadata(): void
    {
        $agent = new AgentListItemResponseSchema();

        self::assertNull($agent->getName());
        self::assertSame($agent, $agent->setName('Assistant'));
        self::assertSame('Assistant', $agent->getName());
        self::assertSame('AgentListItemResponseSchema', $agent->getOpenAPIModelName());
        self::assertInstanceOf(ModelInterface::class, $agent);

        $reference = new ModelReference(['model_name' => 'example']);
        self::assertSame('example', $reference->getModelName());
        self::assertSame('ModelReference', $reference->getOpenAPIModelName());
    }

    public function testNumericEnumValues(): void
    {
        $feedback = new ConversationFeedbackPayloadSchema();

        self::assertSame([-1, 1], $feedback->getFeedbackRateAllowableValues());
        self::assertSame($feedback, $feedback->setFeedbackRate(-1));
        self::assertSame(-1, $feedback->getFeedbackRate());
    }

    public function testGeneratedAccessorTypesMatchSerializerTypes(): void
    {
        foreach (glob(__DIR__ . '/../../src/lib/Model/*.php') as $file) {
            $class = 'Ibexa\\AgentPlatform\\Model\\' . basename($file, '.php');
            if (!class_exists($class) || !is_subclass_of($class, ModelInterface::class)) {
                continue;
            }

            foreach ($class::openAPITypes() as $property => $schemaType) {
                $getter = new \ReflectionMethod($class, $class::getters()[$property]);
                $nativeType = $getter->getReturnType();
                if ($nativeType === null) {
                    continue;
                }

                $expectedType = match (true) {
                    str_ends_with($schemaType, '[]'),
                    str_starts_with($schemaType, 'array<') => 'array',
                    $schemaType === 'integer' => 'int',
                    default => $schemaType,
                };
                self::assertInstanceOf(\ReflectionNamedType::class, $nativeType);
                self::assertSame($expectedType, $nativeType->getName(), $class . '::' . $property);
                self::assertTrue($nativeType->allowsNull(), $class . '::' . $property);

                $setter = new \ReflectionMethod($class, $class::setters()[$property]);
                $parameterType = $setter->getParameters()[0]->getType();
                self::assertInstanceOf(\ReflectionNamedType::class, $parameterType);
                self::assertSame($expectedType, $parameterType->getName(), $class . '::' . $property);
            }
        }
    }

    public function testNullableFieldSerialization(): void
    {
        $payload = new UpdateAgentPayloadSchema();
        $defaults = (array) ObjectSerializer::sanitizeForSerialization($payload);

        self::assertArrayNotHasKey('description', $defaults);

        $payload->setDescription(null);
        self::assertEquals($defaults + ['description' => null], (array) ObjectSerializer::sanitizeForSerialization($payload));

        $payload->setDescription('Updated');
        self::assertEquals($defaults + ['description' => 'Updated'], (array) ObjectSerializer::sanitizeForSerialization($payload));
    }

    public function testEnumAndNestedModelDeserialization(): void
    {
        $execution = ObjectSerializer::deserialize(
            (object) ['status' => ExecutionStatus::SUCCESS],
            ExecutionResponseSchema::class
        );
        self::assertInstanceOf(ExecutionResponseSchema::class, $execution);
        self::assertSame(ExecutionStatus::SUCCESS, $execution->getStatus());

        $list = ObjectSerializer::deserialize(
            (object) ['agents' => [(object) ['id' => 42, 'name' => 'Assistant']], 'total_count' => 1],
            AgentListResponseSchema::class
        );
        self::assertInstanceOf(AgentListResponseSchema::class, $list);
        self::assertInstanceOf(AgentListItemResponseSchema::class, $list->getAgents()[0]);
        self::assertSame(42, $list->getAgents()[0]->getId());
        self::assertSame(1, $list->getTotalCount());
    }

    public function testExecutionResultAcceptsStringsObjectsAndNull(): void
    {
        foreach (['answer', (object) ['answer' => 42], null] as $result) {
            $execution = ObjectSerializer::deserialize(
                (object) ['result' => $result],
                ExecutionResponseSchema::class,
            );
            self::assertInstanceOf(ExecutionResponseSchema::class, $execution);
            self::assertEquals($result, $execution->getResult());
        }
    }

    public function testPublicModelProfileIncludesPricing(): void
    {
        $result = ObjectSerializer::deserialize(
            (object) ['data' => [(object) [
                'id' => 1,
                'current_pricing' => (object) ['currency' => 'USD'],
            ]], 'count' => 1],
            ModelShortlistResponseSchema::class,
        );
        self::assertInstanceOf(ModelShortlistResponseSchema::class, $result);
        $profile = $result->getData()[0];
        self::assertInstanceOf(AdminModelProfileResponseSchema::class, $profile);
        self::assertSame(1, $profile->getId());
        self::assertInstanceOf(AdminModelPricingResponseSchema::class, $profile->getCurrentPricing());
        self::assertSame('USD', $profile->getCurrentPricing()->getCurrency());
    }

    public function testRequestAuthenticationAndResponse(): void
    {
        $request = new MockResponse('{"agents":[],"total_count":0}', [
            'response_headers' => ['Content-Type' => 'application/json'],
        ]);
        $config = (new Configuration())->setHost('https://example.test')->setAccessToken('token');
        $client = new AgentsApi(new MockHttpClient($request), $config);

        $response = $client->listAgents(5, 10);

        self::assertSame([], $response->getAgents());
        self::assertSame(0, $response->getTotalCount());
        self::assertContains('Authorization: Bearer token', $request->getRequestOptions()['headers']);
        self::assertSame('https://example.test/api/v1/agents/?skip=5&limit=10', $request->getRequestUrl());
    }

    public function testHttpErrorIsReported(): void
    {
        $response = new MockResponse('{"detail":"Forbidden"}', [
            'http_code' => 403,
            'response_headers' => ['Content-Type' => 'application/json'],
        ]);
        $client = new AgentsApi(new MockHttpClient($response));

        try {
            $client->listAgents();
            self::fail('Expected an API exception');
        } catch (ApiException $exception) {
            self::assertSame(403, $exception->getCode());
            self::assertSame('{"detail":"Forbidden"}', $exception->getResponseBody());
        }
    }

    public function testAsyncResponse(): void
    {
        $client = new AgentsApi(new MockHttpClient(new MockResponse('{"agents":[],"total_count":0}')));

        $response = $client->listAgentsAsync()->wait();

        self::assertInstanceOf(AgentListResponseSchema::class, $response);
        self::assertSame(0, $response->getTotalCount());
    }
}
