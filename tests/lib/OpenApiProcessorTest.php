<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\AgentPlatform;

use Ibexa\AgentPlatform\Generator\Processor\EventStreamProcessor;
use Ibexa\AgentPlatform\Generator\Processor\ExecutionResultProcessor;
use Ibexa\AgentPlatform\Generator\Processor\ParameterExampleProcessor;
use Ibexa\AgentPlatform\Generator\Processor\ProcessorPipeline;
use Ibexa\AgentPlatform\Generator\Processor\SpecProcessor;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../generator/processors.php';

final class OpenApiProcessorTest extends TestCase
{
    public function testPipelineRunsProcessorsInOrder(): void
    {
        $first = new class() implements SpecProcessor {
            public function process(\stdClass $spec): void
            {
                $spec->steps[] = 'first';
            }
        };
        $second = new class() implements SpecProcessor {
            public function process(\stdClass $spec): void
            {
                $spec->steps[] = 'second';
            }
        };
        $spec = (object) ['steps' => []];

        (new ProcessorPipeline([$first, $second]))->process($spec);

        self::assertSame(['first', 'second'], $spec->steps);
    }

    public function testPipelineStopsOnProcessorFailure(): void
    {
        $failure = new class() implements SpecProcessor {
            public function process(\stdClass $spec): void
            {
                throw new \RuntimeException('Processor failed');
            }
        };
        $later = new class() implements SpecProcessor {
            public function process(\stdClass $spec): void
            {
                $spec->processed = true;
            }
        };
        $spec = new \stdClass();

        try {
            (new ProcessorPipeline([$failure, $later]))->process($spec);
            self::fail('Expected processor failure.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Processor failed', $exception->getMessage());
        }
        self::assertFalse(isset($spec->processed));
    }

    public function testExecutionResultMustMatchExpectedSchema(): void
    {
        $spec = $this->fixture();
        $processor = new ExecutionResultProcessor();
        $processor->process($spec);
        self::assertTrue($spec->components->schemas->ExecutionResponseSchema->properties->result->{'x-unconstrained-value'});

        $spec->components->schemas->ExecutionResponseSchema->properties->result->anyOf = [];
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unexpected execution result schema.');
        $processor->process($spec);
    }

    public function testParameterExamplesIncludeInlineAndReferencedEnums(): void
    {
        $spec = $this->fixture();
        (new ParameterExampleProcessor())->process($spec);
        $parameters = $spec->paths->endpoint->get->parameters;

        self::assertSame("'ready'", $parameters[0]->{'x-contract-example'});
        self::assertSame('42', $parameters[1]->{'x-contract-example'});
        self::assertFalse(isset($parameters[2]->{'x-contract-example'}));
    }

    public function testEventStreamMarksOperationsAndDoesNotMutateSourceSchema(): void
    {
        $spec = $this->fixture();
        $original = $spec->paths->endpoint->get->responses->{'200'}->content->{'text/event-stream'}->itemSchema;
        (new EventStreamProcessor())->process($spec);

        self::assertTrue($spec->paths->endpoint->get->{'x-sse-events'});
        self::assertSame('string', $original->properties->data->type);
        self::assertSame('object', $spec->components->schemas->ServerSentEventSchema->properties->data->type);
        self::assertNotSame($original, $spec->components->schemas->ServerSentEventSchema);
    }

    /** @dataProvider invalidEventSchemaProvider */
    public function testInvalidEventSchemasAreRejected(
        string $case,
        string $message
    ): void {
        $spec = $this->fixture();
        $response = $spec->paths->endpoint->get->responses->{'200'};
        switch ($case) {
            case 'missing':
                unset($response->content->{'text/event-stream'});
                break;
            case 'existing':
                $spec->components->schemas->ServerSentEventSchema = new \stdClass();
                break;
            case 'properties':
                $response->content->{'text/event-stream'}->itemSchema->properties = null;
                break;
            case 'incompatible':
                $spec->paths->another = (object) ['get' => (object) [
                    'responses' => (object) ['200' => (object) [
                        'content' => (object) ['text/event-stream' => (object) [
                            'itemSchema' => (object) ['properties' => (object) ['different' => (object) []]],
                        ]],
                    ]],
                ]];
                break;
            case 'non-object':
                $response->content->{'text/event-stream'}->itemSchema = 'invalid';
                break;
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($message);
        (new EventStreamProcessor())->process($spec);
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidEventSchemaProvider(): iterable
    {
        yield 'missing event schema' => ['missing', 'Cannot generate the server-sent event model.'];
        yield 'existing model' => ['existing', 'Cannot generate the server-sent event model.'];
        yield 'invalid properties' => ['properties', 'Invalid server-sent event properties.'];
        yield 'incompatible schemas' => ['incompatible', 'Streaming endpoints have incompatible event schemas.'];
        yield 'non-object schema' => ['non-object', 'Streaming endpoints have incompatible event schemas.'];
    }

    private function fixture(): \stdClass
    {
        return json_decode(<<<'JSON'
        {
          "components": {"schemas": {
            "ExecutionResponseSchema": {"properties": {"result": {"anyOf": [{}, {"type": "null"}]}}},
            "Status": {"enum": [42]}
          }},
          "paths": {"endpoint": {"get": {
            "parameters": [
              {"schema": {"enum": ["ready"]}},
              {"schema": {"$ref": "#/components/schemas/Status"}},
              {"schema": {"type": "string"}}
            ],
            "responses": {"200": {"content": {"text/event-stream": {
              "itemSchema": {"properties": {"data": {"type": "string"}}}
            }}}}
          }}}
        }
        JSON, false, 512, JSON_THROW_ON_ERROR);
    }
}
