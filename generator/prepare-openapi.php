<?php

declare(strict_types=1);

$spec = json_decode(file_get_contents(__DIR__ . '/openapi.json'), false, 512, JSON_THROW_ON_ERROR);
$resultSchema = $spec->components->schemas->ExecutionResponseSchema->properties->result;
if (($resultSchema->anyOf ?? null) != [(object) [], (object) ['type' => 'null']]) {
    throw new RuntimeException('Unexpected execution result schema.');
}
$resultSchema->{'x-unconstrained-value'} = true;
$eventSchema = null;
$count = 0;

foreach ($spec->paths as $methods) {
    foreach ($methods as &$operation) {
        if (!is_object($operation) || !isset($operation->responses)) {
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
        foreach ($operation->responses as $response) {
            $itemSchema = $response->content->{'text/event-stream'}->itemSchema ?? null;
            if ($itemSchema === null) {
                continue;
            }
            if (!is_object($itemSchema) || ($eventSchema !== null && $eventSchema != $itemSchema)) {
                throw new RuntimeException('Streaming endpoints have incompatible event schemas.');
            }
            $eventSchema = $itemSchema;
            $operation->{'x-sse-events'} = true;
            ++$count;
        }
    }
    unset($operation);
}
unset($methods);

if ($count === 0 || isset($spec->components->schemas->ServerSentEventSchema)) {
    throw new RuntimeException('Cannot generate the server-sent event model.');
}

$eventSchema = clone $eventSchema;
$eventSchema->properties = clone $eventSchema->properties;
$eventSchema->properties->data = (object) [
    'type' => 'object',
    'additionalProperties' => new stdClass(),
    'description' => 'The JSON event payload decoded as an associative array.',
];
$spec->components->schemas->ServerSentEventSchema = $eventSchema;
echo json_encode($spec, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
