# Configuration

## Gateway options

| Option | Required | Description |
| --- | --- | --- |
| `environment` | yes | `secure` for production (`https://secure.payu.com`) or `sandbox` (`https://secure.snd.payu.com`). Defaults to `secure`. |
| `pos_id` | yes | Point of sale ID (`merchantPosId`). |
| `signature_key` | yes | The POS "second key (MD5)". Used to verify notification signatures. |
| `oauth_client_id` | yes | OAuth client ID of the POS. |
| `oauth_client_secret` | yes | OAuth client secret of the POS. |

The options map one to one to the fields PayU shows under **My shops → Point of sale → Configuration keys**. Payum
validates that every required option is set and the package rejects an unknown `environment` with
`PayumPayU\Exception\InvalidArgumentException`.

## Service overrides

Infrastructure is injected through `payum.payu.*` keys of the gateway config. Each accepts either an instance or a
closure receiving Payum's config `ArrayObject` and returning the instance.

| Key | Constant on `PayUGatewayFactory` | Type | Default |
| --- | --- | --- | --- |
| `payum.payu.http_client` | `CONFIG_HTTP_CLIENT` | `Psr\Http\Client\ClientInterface` | `Psr18ClientDiscovery::find()` |
| `payum.payu.request_factory` | `CONFIG_REQUEST_FACTORY` | `Psr\Http\Message\RequestFactoryInterface` | `Psr17FactoryDiscovery::findRequestFactory()` |
| `payum.payu.stream_factory` | `CONFIG_STREAM_FACTORY` | `Psr\Http\Message\StreamFactoryInterface` | `Psr17FactoryDiscovery::findStreamFactory()` |
| `payum.payu.clock` | `CONFIG_CLOCK` | `Psr\Clock\ClockInterface` | `PayumPayU\Clock\SystemClock` |
| `payum.payu.header_provider` | `CONFIG_HEADER_PROVIDER` | `PayumPayU\Http\HeaderProviderInterface` | `ServerHeaderProvider::fromGlobals()` |

Overrides can be passed as factory defaults (apply to every gateway built by that factory) or per gateway:

```php
$factory = new PayUGatewayFactory([
    PayUGatewayFactory::CONFIG_HTTP_CLIENT => $psr18Client,
    PayUGatewayFactory::CONFIG_CLOCK => $clock,
]);
```

The header provider exists because Payum's `GetHttpRequest` does not expose HTTP headers and PayU signs notifications in
the `OpenPayu-Signature` header. The default reads `$_SERVER`; the Symfony bundle reads the current `Request`.

## Replacing the whole API layer

Set `payum.api` to your own `PayumPayU\Api` instance to bypass option validation and the built-in REST client:

```php
use PayumPayU\Api;
use PayumPayU\Rest\Configuration;
use PayumPayU\Rest\Environment;

$config['payum.api'] = new Api(
    new Configuration(Environment::Sandbox, '300746', 'second-key', 'client-id', 'client-secret'),
    $myClientImplementingRestClientInterface,
);
```

`PayumPayU\Rest\ClientInterface` has five methods: `createOrder`, `retrieveOrder`, `cancelOrder`, `createRefund`,
`retrievePayMethods`.

## OAuth tokens

Bearer tokens are requested with `client_credentials` for merchant calls and `trusted_merchant` for calls on behalf of a
buyer (stored card tokens). They are cached in memory per grant and renewed one minute before they expire, using the
configured clock.
