# Installation

```bash
composer require payum-payu/payum-payu
```

## HTTP client

The gateway talks to PayU through a [PSR-18](https://www.php-fig.org/psr/psr-18/) client and builds requests with
[PSR-17](https://www.php-fig.org/psr/psr-17/) factories. Both are discovered automatically through
`php-http/discovery`; install any implementation you like:

```bash
composer require symfony/http-client nyholm/psr7
# or
composer require guzzlehttp/guzzle
```

**The client must not follow redirects.** PayU answers `POST /api/v2_1/orders` with HTTP 302 and the JSON result in the
body. Guzzle's PSR-18 mode already disables redirects. Symfony's `Psr18Client` follows them by default, so pass it
explicitly:

```php
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use PayumPayU\PayUGatewayFactory;

$factory = new PayUGatewayFactory([
    PayUGatewayFactory::CONFIG_HTTP_CLIENT => new Psr18Client(HttpClient::create(['max_redirects' => 0])),
]);
```

The Symfony bundle does this for you ([symfony.md](symfony.md)).

## Payum's own HTTP client

Payum core still wires an HTTPlug client of its own for other gateways. If your project has none, Payum throws
`Http\Discovery\NotFoundException` while building the gateway. Either install an adapter
(`composer require php-http/guzzle7-adapter`) or set `payum.http_client` in the gateway config. This package does not
use that client.

## Registering the gateway factory

```php
use Payum\Core\PayumBuilder;
use PayumPayU\PayUGatewayFactory;

$payum = (new PayumBuilder())
    ->addDefaultStorages()
    ->addGatewayFactory(PayUGatewayFactory::NAME, new PayUGatewayFactory())
    ->addGateway('payu', [
        'factory' => PayUGatewayFactory::NAME,
        'environment' => 'sandbox',
        'pos_id' => '300746',
        'signature_key' => 'second-key-from-the-panel',
        'oauth_client_id' => '300746',
        'oauth_client_secret' => 'client-secret-from-the-panel',
    ])
    ->getPayum();
```

All options are explained in [configuration.md](configuration.md).

## PayU sandbox

Create a sandbox account at <https://registration-merch-prod.snd.payu.com/>, open **My shops → Point of sale**, and copy
the POS ID, the second key (MD5) and the OAuth client credentials. Use `environment: sandbox`.
