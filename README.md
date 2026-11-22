# Ibexa Agentic Marketing Platform API

For installation, authentication, usage examples, API overview, and architecture,
see the [PHP developer guide](docs/README.md).

## Installation & Usage

### Requirements

PHP 8.3–8.x with the `curl`, `json`, and `mbstring` extensions, and Composer.
The generated clients use Symfony HttpClient. PSR-7 request construction
uses `guzzlehttp/psr7`; the Guzzle HTTP client is not required.

### Composer

To install the bindings via [Composer](https://getcomposer.org/), add the following to `composer.json`:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/ibexa/agent-platform-php-api.git"
    }
  ],
  "require": {
    "ibexa/agent-platform-api": "*@dev"
  }
}
```

Then run `composer install`. If the package is already available through a
configured Composer repository, omit the VCS repository entry. Pin an
appropriate release or branch for deployments.

## Getting Started

Configure the API origin and authenticate before using protected endpoints.
Set the environment variables `AGENT_PLATFORM_USERNAME` and
`AGENT_PLATFORM_PASSWORD` using your application's secret management.

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Ibexa\AgentPlatform\Client\AgentsApi;
use Ibexa\AgentPlatform\Client\LoginApi;
use Ibexa\AgentPlatform\Configuration;
use Symfony\Component\HttpClient\HttpClient;

$username = getenv('AGENT_PLATFORM_USERNAME');
$password = getenv('AGENT_PLATFORM_PASSWORD');
if (!$username || !$password) {
    throw new RuntimeException('Agent Platform credentials are required');
}

$config = (new Configuration())->setHost('https://api.ibexa.ai');
$httpClient = HttpClient::create();
$tokens = (new LoginApi($httpClient, $config))->login($username, $password);
$config->setAccessToken($tokens->getAccessToken());

$agents = (new AgentsApi($httpClient, $config))->listAgents();
foreach ($agents->getAgents() as $agent) {
    echo $agent->getId() . ': ' . $agent->getName() . PHP_EOL;
}
```

Replace the host with your deployment's origin, without `/api/v1`. See the
[developer guide](docs/README.md) for token refresh, error handling, and more
examples.

## Asynchronous requests and streaming

Async methods start a request and return an `AsyncResult`. Call `wait()` to
obtain the same model as the synchronous method; `*AsyncWithHttpInfo()->wait()`
returns `[$model, $statusCode, $headers]`. Multiple requests can be started
before waiting. Results and exceptions are cached on repeated calls to `wait()`.

```php
use Ibexa\AgentPlatform\Client\AgentsApi;

$agentsApi = new AgentsApi($httpClient, $config);
$pending = $agentsApi->listAgentsAsync();
$agents = $pending->wait();
```

Streaming operations expose generated `*StreamEvents()` methods that yield
`ServerSentEventSchema` objects:

```php
use Ibexa\AgentPlatform\Client\ExecutionsApi;
$executions = new ExecutionsApi($httpClient, $config);
foreach ($executions->attachExecutionStreamEvents(42) as $event) {
    $payload = $event->getData();
    var_dump($event->getEvent(), $payload);
}
```

`MessagesApi` also provides `attachMessageStreamEvents()` and
`sendMessageStreamEvents()`. The event model exposes `getData()`, `getEvent()`,
`getId()` and `getRetry()`; retry is in milliseconds. Symfony defaults missing
event types to `message`, missing IDs to an empty string, and missing retry to
zero. `getData()` returns the JSON payload decoded as an associative array,
including nested objects. Invalid JSON and non-array payloads raise `ApiException`.

Automatic reconnection and redirects are disabled, including for POST streams.
For attachment streams, callers can explicitly reconnect with the last event ID.
Release the iterator when stopping early to cancel the response. HTTP and network
errors raise `ApiException`. The older generated stream convenience methods do
not return stream data. `StreamClient::stream()` remains available for raw bytes.

This migration changes constructor injection from Guzzle to Symfony's
`HttpClientInterface` and replaces Guzzle promises with `AsyncResult`; `then()`
and other Guzzle promise APIs are no longer available.

## Regenerating the client

The client is generated from the checked-in `generator/openapi.json` snapshot
using OpenAPI Generator 7.27.0 and the overrides in `generator/templates`.
Install Composer dependencies and Docker, then run `./update.sh` from any
directory. The preprocessing step derives a shared event schema and marks SSE
operations before generation; it fails if their item schemas differ. Update the
snapshot deliberately when adopting API changes; the script does not fetch the live API.
The snapshot contains streaming response `itemSchema` fields which the
generator's validator does not recognize, so generation skips validation.

Generated models expose native types for scalar and collection accessors and
return `static` from setters. Scalar and collection getters remain nullable
because a model can be constructed before required values are populated.
The model metadata method is `getOpenAPIModelName()`; `getModelName()` is
reserved for a schema field of that name.
The `HeaderSelector` template override keeps Accept-header quality weights
integer-valued for strict PHP type checking.
The supporting `ApiException`, `Configuration`, and `ObjectSerializer`
templates also provide native type hints. Configuration setters enforce their
documented argument types; serializer values that can hold different kinds of
data remain `mixed`.

## Tests and coverage

`./update.sh` also generates endpoint contract tests and model serialization
checks under `tests/generated`. These files are replaced on regeneration; keep
handwritten tests and realistic response fixtures under `tests/lib`.
The PHP generator hardcodes its test directory; `update.sh` relocates the
generated files into `tests/generated` before formatting them.
The generated suite checks request methods, paths and authentication, HTTP error
handling, response types and SSE payload decoding. Its minimal fixtures do not
replace API-specific validation and integration tests.

Run `composer phpstan` for PHPStan level 2 analysis of the client, tests and
OpenAPI preprocessing script. CI runs the same check on PHP 8.3. The unused
constructor parameter warning is excluded for generated models, which share a
constructor signature even when they have no properties.

Run all suites with `composer test`, or only generated tests with
`composer test -- --testsuite generated`. To generate HTML and Clover coverage
reports, enable Xdebug coverage or PCOV:

```bash
XDEBUG_MODE=coverage composer test-coverage
```

Reports are written to `build/coverage/` and `build/coverage.xml`. Coverage
includes `src/lib`, not generated test files. CI publishes the reports for PHP
8.3 and checks that regenerated clients and tests match the committed files.

## COPYRIGHT

Copyright (C) 1999-2026 Ibexa AS (formerly eZ Systems AS). All rights reserved.

## LICENSE

This source code is available separately under the following licenses:

A - Ibexa Business Use License Agreement (Ibexa BUL),
version 2.4 or later versions (as license terms may be updated from time to time)
Ibexa BUL is granted by having a valid Ibexa DXP (formerly eZ Platform Enterprise) subscription,
as described at: https://www.ibexa.co/product
For the full Ibexa BUL license text, please see:
- LICENSE-bul file placed in the root of this source code, or
- https://www.ibexa.co/software-information/licenses-and-agreements (latest version applies)

AND

B - GNU General Public License, version 2
Grants an copyleft open source license with ABSOLUTELY NO WARRANTY. For the full GPL license text, please see:
- LICENSE file placed in the root of this source code, or
- https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
