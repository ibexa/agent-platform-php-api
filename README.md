# Ibexa Agentic Marketing Platform API

PHP client for the Ibexa Agentic Marketing Platform REST API.

## Installation & Usage

### Requirements

PHP 8.3–8.x with the `curl`, `json`, and `mbstring` extensions, and Composer.
The generated clients use Symfony HttpClient. PSR-7 request construction
uses `guzzlehttp/psr7`; the Guzzle HTTP client is not required.

### Composer

Install the package from [Packagist](https://packagist.org/packages/ibexa/agent-platform-api)
using [Composer](https://getcomposer.org/):

```bash
composer require ibexa/agent-platform-api
```

## Getting Started

Configure the API origin and authenticate before using protected endpoints.
Set the environment variables `IAMP_USERNAME` and `IAMP_PASSWORD` using your
application's secret management.

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Ibexa\AgentPlatform\Client\AgentsApi;
use Ibexa\AgentPlatform\Client\LoginApi;
use Ibexa\AgentPlatform\Configuration;
use Symfony\Component\HttpClient\HttpClient;

$username = getenv('IAMP_USERNAME');
$password = getenv('IAMP_PASSWORD');
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

Replace the host with your deployment's origin, without `/api/v1`.

## Asynchronous requests and streaming

Async methods start a request and return an `AsyncResult`. Call `wait()` to
obtain the result; multiple requests can be started before waiting.

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
`sendMessageStreamEvents()`. Event data is decoded into an array. Streams do
not reconnect automatically; attachment streams can be reconnected using the
last event ID. HTTP, network, and invalid event payload errors raise `ApiException`.

## Regenerating the client

Contributors can regenerate the client from the checked-in
`generator/openapi.json` snapshot. Install Composer dependencies and Docker,
then run:

```bash
./update.sh
```

The script does not fetch a newer API specification.

## Tests and coverage

Run the test suite, static analysis, and style checks with:

```bash
composer test
composer phpstan
composer check-cs
```

Use `composer fix-cs` to apply style fixes. To generate coverage reports,
enable Xdebug coverage or PCOV:

```bash
XDEBUG_MODE=coverage composer test-coverage
```

Reports are written to `build/coverage/` and `build/coverage.xml`.

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
