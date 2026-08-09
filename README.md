# agentic-platform-api

## Installation & Usage

### Requirements

PHP 8.1 and later.

### Composer

To install the bindings via [Composer](https://getcomposer.org/), add the following to `composer.json`:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/ibexa/agent-platform-api.git"
    }
  ],
  "require": {
    "ibexa/agent-platform-api": "*@dev"
  }
}
```

Then run `composer install`

## Getting Started

Please follow the [installation procedure](#installation--usage) and then run the following:

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');

$apiInstance = new Ibexa\AgentPlatform\Api\AccountActivationApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client()
);
$token = 'token_example'; // string
$activate_account_request_schema = new \Ibexa\AgentPlatform\Model\ActivateAccountRequestSchema(); // \Ibexa\AgentPlatform\Model\ActivateAccountRequestSchema

try {
    $result = $apiInstance->accountActivationActivateAccount($token, $activate_account_request_schema);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling AccountActivationApi->accountActivationActivateAccount: ', $e->getMessage(), PHP_EOL;
}

```

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
