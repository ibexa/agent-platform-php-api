# Ibexa Agentic Marketing Platform API

For installation, authentication, usage examples, API overview, and architecture,
see the [PHP developer guide](docs/README.md).

## Installation & Usage

### Requirements

PHP 8.3–8.x with the `curl`, `json`, and `mbstring` extensions, and Composer.
The generated clients use Guzzle for synchronous and asynchronous requests.

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

use GuzzleHttp\Client;
use Ibexa\AgentPlatform\Client\AgentsApi;
use Ibexa\AgentPlatform\Client\LoginApi;
use Ibexa\AgentPlatform\Configuration;

$username = getenv('AGENT_PLATFORM_USERNAME');
$password = getenv('AGENT_PLATFORM_PASSWORD');
if (!$username || !$password) {
    throw new RuntimeException('Agent Platform credentials are required');
}

$config = (new Configuration())->setHost('https://api.ibexa.ai');
$httpClient = new Client();
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

## Regenerating the client

The client is generated from the checked-in `generator/openapi.json` snapshot
using OpenAPI Generator 7.27.0 and the overrides in `generator/templates`.
Install Docker, then run `./update.sh` from any directory. Update the snapshot
deliberately when adopting API changes; the script does not fetch the live API.
The snapshot contains streaming response `itemSchema` fields which the
generator's validator does not recognize, so generation skips validation.

Generated models expose native types for scalar and collection accessors and
return `static` from setters. Scalar and collection getters remain nullable
because a model can be constructed before required values are populated.
The model metadata method is `getOpenAPIModelName()`; `getModelName()` is
reserved for a schema field of that name.
The `HeaderSelector` template override keeps Accept-header quality weights
integer-valued for strict PHP type checking.

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
