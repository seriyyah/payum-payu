# payum-payu

PayU gateway for [Payum](https://github.com/Payum/Payum), built on the PayU REST API 2.1.

[![CI](https://github.com/payum-payu/payum-payu/actions/workflows/ci.yml/badge.svg)](https://github.com/payum-payu/payum-payu/actions/workflows/ci.yml)
[![Packagist](https://img.shields.io/packagist/v/payum-payu/payum-payu.svg)](https://packagist.org/packages/payum-payu/payum-payu)
[![PHP](https://img.shields.io/packagist/php-v/payum-payu/payum-payu.svg)](composer.json)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

- Hosted checkout: create the order, redirect the buyer, pick up the result when they return.
- Signed server-to-server notifications with out-of-order protection.
- Card-token recurring payments (`FIRST` and `STANDARD`), with automatic lookup of the buyer's stored card.
- Refunds (full and partial), cancellation, status synchronisation.
- Framework-agnostic core on PSR-18 / PSR-17 / PSR-20; optional Symfony bundle.
- Typed responses, package-specific exceptions, PHPStan level 10, 100% test coverage, mutation tested.

## Requirements

- PHP 8.2 or newer
- `payum/core` 1.7.7 or newer
- A PSR-18 HTTP client and PSR-17 factories, for example `symfony/http-client` + `nyholm/psr7` or `guzzlehttp/guzzle`

## Installation

```bash
composer require payum-payu/payum-payu
```

Releases are tagged from `main`; the next release is prepared on `dev`, installable as `1.x-dev`:

```bash
composer require payum-payu/payum-payu:1.x-dev
```

Symfony projects using PayumBundle: see [docs/symfony.md](docs/symfony.md). Everyone else: [docs/installation.md](docs/installation.md).

## Quick start (plain PHP)

```php
use Payum\Core\PayumBuilder;
use Payum\Core\Request\Capture;
use Payum\Core\Request\GetHumanStatus;
use PayumPayU\PayUGatewayFactory;

$payum = (new PayumBuilder())
    ->addDefaultStorages()
    ->addGatewayFactory(PayUGatewayFactory::NAME, new PayUGatewayFactory())
    ->addGateway('payu', [
        'factory' => PayUGatewayFactory::NAME,
        'environment' => 'sandbox',          // or "secure" for production
        'pos_id' => '300746',
        'signature_key' => '...',            // "Second key (MD5)" in the PayU panel
        'oauth_client_id' => '300746',
        'oauth_client_secret' => '...',
    ])
    ->getPayum();

$payment = $payum->getStorage(Payment::class)->create();
$payment->setNumber('INV-2026-0001');
$payment->setCurrencyCode('PLN');
$payment->setTotalAmount(2100);               // minor units: 21.00 PLN
$payment->setDescription('Insurance premium');
$payment->setClientId('customer-42');
$payment->setClientEmail('buyer@example.com');

$token = $payum->getTokenFactory()->createCaptureToken('payu', $payment, 'payment-done.php');
header('Location: ' . $token->getTargetUrl());
```

On `payment-done.php`, run `GetHumanStatus` to read the outcome. The full flow, including what to store and when PayU calls you back, is described in [docs/payment-flow.md](docs/payment-flow.md).

## Documentation

| Topic | Page |
| --- | --- |
| Installing in any PHP project | [docs/installation.md](docs/installation.md) |
| Gateway options and service overrides | [docs/configuration.md](docs/configuration.md) |
| Capture, redirect, return, status | [docs/payment-flow.md](docs/payment-flow.md) |
| Notifications (webhooks) and signatures | [docs/notifications.md](docs/notifications.md) |
| Recurring payments with card tokens | [docs/recurring-payments.md](docs/recurring-payments.md) |
| Refunds and cancellation | [docs/refunds-and-cancellation.md](docs/refunds-and-cancellation.md) |
| Symfony and PayumBundle | [docs/symfony.md](docs/symfony.md) |
| Payment details reference | [docs/details-reference.md](docs/details-reference.md) |
| Migrating from accesto/payum-pay-u | [docs/migrating-from-accesto.md](docs/migrating-from-accesto.md) |
| Development and quality tooling | [docs/development.md](docs/development.md) |

## Versioning and support

Semantic versioning, see [CONTRIBUTING.md](CONTRIBUTING.md) for what counts as a breaking change and how releases are cut.
Supported PHP versions follow the official PHP support schedule. Security issues: see [SECURITY.md](SECURITY.md).

## Credits and license

Created and maintained by [Serhii Puzikov](https://github.com/puzikov). Released under the [MIT license](LICENSE): use it, fork it,
change it, ship it in commercial products; keep the copyright notice. This package is not affiliated with PayU S.A. or
the Payum project.
