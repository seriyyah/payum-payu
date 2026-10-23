# Symfony

Requires `payum/payum-bundle` 2.6 or newer and Symfony 6.4, 7 or 8.

```bash
composer require payum-payu/payum-payu payum/payum-bundle symfony/http-client nyholm/psr7
```

## Register the bundle

```php
// config/bundles.php
return [
    // ...
    Payum\Bundle\PayumBundle\PayumBundle::class => ['all' => true],
    PayumPayU\Bridge\Symfony\PayumPayUBundle::class => ['all' => true],
];
```

The bundle registers:

| Service | Purpose |
| --- | --- |
| `payum_payu.gateway_factory_builder` | tagged `payum.gateway_factory_builder` with `factory: payu`, so PayumBundle knows the `payu` factory |
| `payum_payu.header_provider` | reads `OpenPayu-Signature` from the current `Request` |
| `payum_payu.http_client` | `Psr18Client` over `HttpClient::create(['max_redirects' => 0])`, only when `symfony/http-client` is installed |

No bundle configuration is needed. To use your own PSR-18 client instead, override the service:

```yaml
# config/services.yaml
services:
    payum_payu.http_client: '@App\Http\MyPsr18Client'
```

Remember that the client must not follow redirects ([installation.md](installation.md)).

## Configure a gateway

```yaml
# config/packages/payum.yaml
payum:
    gateways:
        payu:
            factory: payu
            environment: '%env(PAYU_ENVIRONMENT)%'
            pos_id: '%env(PAYU_POS_ID)%'
            signature_key: '%env(PAYU_SIGNATURE_KEY)%'
            oauth_client_id: '%env(PAYU_OAUTH_CLIENT_ID)%'
            oauth_client_secret: '%env(PAYU_OAUTH_CLIENT_SECRET)%'
```

Dynamic gateways (configuration stored in the database through `payum.dynamic_gateways`) work the same way; the option
names are the keys of the stored configuration.

## Without the bundle

Register the factory yourself if you prefer explicit wiring:

```yaml
services:
    payum.payu.gateway_factory:
        class: PayumPayU\Bridge\Symfony\GatewayFactoryBuilder
        arguments:
            - '@PayumPayU\Bridge\Symfony\RequestStackHeaderProvider'
            - '@my.psr18.client'
        tags:
            - { name: payum.gateway_factory_builder, factory: payu }

    PayumPayU\Bridge\Symfony\RequestStackHeaderProvider: ~
```

## Reacting to notifications

Listen to `payum.gateway.post_execute` as shown in [notifications.md](notifications.md); PayumBundle dispatches it
through the Symfony event dispatcher.
