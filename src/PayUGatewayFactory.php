<?php

declare(strict_types=1);

namespace PayumPayU;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\GatewayFactory;
use PayumPayU\Action\CancelAction;
use PayumPayU\Action\CaptureAction;
use PayumPayU\Action\ConvertPaymentAction;
use PayumPayU\Action\CreateOrderAction;
use PayumPayU\Action\NotifyAction;
use PayumPayU\Action\RefundAction;
use PayumPayU\Action\RefundOrderAction;
use PayumPayU\Action\RetrievePayMethodsAction;
use PayumPayU\Action\StatusAction;
use PayumPayU\Action\SyncAction;
use PayumPayU\Clock\SystemClock;
use PayumPayU\Exception\InvalidArgumentException;
use PayumPayU\Exception\LogicException;
use PayumPayU\Http\HeaderProviderInterface;
use PayumPayU\Http\ServerHeaderProvider;
use PayumPayU\Notification\NotificationParser;
use PayumPayU\Notification\SignatureVerifier;
use PayumPayU\Order\PayloadFactory;
use PayumPayU\Recurring\CardTokenSelector;
use PayumPayU\Rest\Client;
use PayumPayU\Rest\Configuration;
use PayumPayU\Rest\Environment;
use PayumPayU\Rest\OAuth\TokenProvider;
use PayumPayU\Rest\Transport;
use Psr\Clock\ClockInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Gateway options: environment ("secure" or "sandbox"), pos_id,
 * signature_key, oauth_client_id, oauth_client_secret.
 *
 * Infrastructure can be swapped through the payum.payu.* config keys; by
 * default the PSR-18 client and PSR-17 factories are discovered.
 */
final class PayUGatewayFactory extends GatewayFactory
{
    public const NAME = 'payu';

    public const OPTION_ENVIRONMENT = 'environment';

    public const OPTION_POS_ID = 'pos_id';

    public const OPTION_SIGNATURE_KEY = 'signature_key';

    public const OPTION_OAUTH_CLIENT_ID = 'oauth_client_id';

    public const OPTION_OAUTH_CLIENT_SECRET = 'oauth_client_secret';

    public const CONFIG_HTTP_CLIENT = 'payum.payu.http_client';

    public const CONFIG_REQUEST_FACTORY = 'payum.payu.request_factory';

    public const CONFIG_STREAM_FACTORY = 'payum.payu.stream_factory';

    public const CONFIG_CLOCK = 'payum.payu.clock';

    public const CONFIG_HEADER_PROVIDER = 'payum.payu.header_provider';

    private const REQUIRED_OPTIONS = [
        self::OPTION_ENVIRONMENT,
        self::OPTION_POS_ID,
        self::OPTION_SIGNATURE_KEY,
        self::OPTION_OAUTH_CLIENT_ID,
        self::OPTION_OAUTH_CLIENT_SECRET,
    ];

    protected function populateConfig(ArrayObject $config): void
    {
        $config->defaults([
            'payum.factory_name' => self::NAME,
            'payum.factory_title' => 'PayU',
            'payum.action.capture' => new CaptureAction(),
            'payum.action.convert_payment' => new ConvertPaymentAction(),
            'payum.action.status' => new StatusAction(),
            'payum.action.sync' => new SyncAction(),
            'payum.action.refund' => new RefundAction(),
            'payum.action.cancel' => new CancelAction(),
            'payum.action.create_order' => new CreateOrderAction(new PayloadFactory(), new CardTokenSelector()),
            'payum.action.refund_order' => new RefundOrderAction(),
            'payum.action.retrieve_pay_methods' => new RetrievePayMethodsAction(),
            'payum.action.notify' => fn (ArrayObject $config): NotifyAction => new NotifyAction(
                $this->service($config, self::CONFIG_HEADER_PROVIDER, HeaderProviderInterface::class),
                new SignatureVerifier(),
                new NotificationParser(),
            ),
            self::CONFIG_HTTP_CLIENT => Psr18ClientDiscovery::find(...),
            self::CONFIG_REQUEST_FACTORY => Psr17FactoryDiscovery::findRequestFactory(...),
            self::CONFIG_STREAM_FACTORY => Psr17FactoryDiscovery::findStreamFactory(...),
            self::CONFIG_CLOCK => static fn (): ClockInterface => new SystemClock(),
            self::CONFIG_HEADER_PROVIDER => ServerHeaderProvider::fromGlobals(...),
        ]);

        if ($config['payum.api'] !== null) {
            return;
        }

        $config['payum.default_options'] = [
            self::OPTION_ENVIRONMENT => Environment::Production->value,
            self::OPTION_POS_ID => '',
            self::OPTION_SIGNATURE_KEY => '',
            self::OPTION_OAUTH_CLIENT_ID => '',
            self::OPTION_OAUTH_CLIENT_SECRET => '',
        ];
        $config->defaults($config['payum.default_options']);
        $config['payum.required_options'] = self::REQUIRED_OPTIONS;
        $config['payum.api'] = fn (ArrayObject $config): Api => $this->createApi($config);
    }

    private function createApi(ArrayObject $config): Api
    {
        $config->validateNotEmpty(self::REQUIRED_OPTIONS);

        $environment = $this->stringOption($config, self::OPTION_ENVIRONMENT);
        $configuration = new Configuration(
            Environment::tryFrom($environment) ?? throw new InvalidArgumentException(sprintf(
                'The option "%s" must be "%s" or "%s", got "%s".',
                self::OPTION_ENVIRONMENT,
                Environment::Production->value,
                Environment::Sandbox->value,
                $environment,
            )),
            $this->stringOption($config, self::OPTION_POS_ID),
            $this->stringOption($config, self::OPTION_SIGNATURE_KEY),
            $this->stringOption($config, self::OPTION_OAUTH_CLIENT_ID),
            $this->stringOption($config, self::OPTION_OAUTH_CLIENT_SECRET),
        );

        $transport = new Transport(
            $this->service($config, self::CONFIG_HTTP_CLIENT, ClientInterface::class),
            $this->service($config, self::CONFIG_REQUEST_FACTORY, RequestFactoryInterface::class),
            $this->service($config, self::CONFIG_STREAM_FACTORY, StreamFactoryInterface::class),
        );
        $tokenProvider = new TokenProvider($transport, $configuration, $this->service($config, self::CONFIG_CLOCK, ClockInterface::class));

        return new Api($configuration, new Client($transport, $tokenProvider, $configuration));
    }

    private function stringOption(ArrayObject $config, string $option): string
    {
        $value = $config[$option];

        if (! is_string($value)) {
            throw new InvalidArgumentException(sprintf('The option "%s" must be a string.', $option));
        }

        return $value;
    }

    /**
     * Resolves a payum.payu.* entry, which may be the service itself or a
     * closure receiving the config and producing it.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(ArrayObject $config, string $key, string $class): object
    {
        $value = $config[$key];

        if ($value instanceof \Closure) {
            $value = $value($config);
        }

        if (! $value instanceof $class) {
            throw new LogicException(sprintf('The config entry "%s" must be an instance of %s.', $key, $class));
        }

        return $value;
    }
}
