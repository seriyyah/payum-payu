<?php

declare(strict_types=1);

namespace PayumPayU\Bridge\Symfony;

use PayumPayU\PayUGatewayFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ReferenceConfigurator;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * Registers the "payu" gateway factory with PayumBundle. When
 * symfony/http-client is installed a PSR-18 client that does not follow
 * redirects is wired in, as PayU answers order creation with HTTP 302.
 */
final class PayumPayUBundle extends AbstractBundle
{
    public const SERVICE_HEADER_PROVIDER = 'payum_payu.header_provider';

    public const SERVICE_HTTP_CLIENT = 'payum_payu.http_client';

    public const SERVICE_GATEWAY_FACTORY_BUILDER = 'payum_payu.gateway_factory_builder';

    private const SERVICE_SYMFONY_HTTP_CLIENT = 'payum_payu.http_client.symfony';

    private const TAG_GATEWAY_FACTORY_BUILDER = 'payum.gateway_factory_builder';

    public function __construct(
        private readonly bool $wireSymfonyHttpClient = true,
    ) {
    }

    /**
     * @param array<mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();

        $services->set(self::SERVICE_HEADER_PROVIDER, RequestStackHeaderProvider::class)
            ->args([service('request_stack')]);

        $services->set(self::SERVICE_GATEWAY_FACTORY_BUILDER, GatewayFactoryBuilder::class)
            ->args([service(self::SERVICE_HEADER_PROVIDER), $this->httpClientReference($container)])
            ->tag(self::TAG_GATEWAY_FACTORY_BUILDER, [
                'factory' => PayUGatewayFactory::NAME,
            ]);
    }

    private function httpClientReference(ContainerConfigurator $container): ?ReferenceConfigurator
    {
        if (!$this->wireSymfonyHttpClient || !class_exists(Psr18Client::class)) {
            return null;
        }

        $services = $container->services();

        $services->set(self::SERVICE_SYMFONY_HTTP_CLIENT, HttpClientInterface::class)
            ->factory([HttpClient::class, 'create'])
            ->args([[
                'max_redirects' => 0,
            ]]);

        $services->set(self::SERVICE_HTTP_CLIENT, Psr18Client::class)
            ->args([service(self::SERVICE_SYMFONY_HTTP_CLIENT)]);

        return service(self::SERVICE_HTTP_CLIENT);
    }
}
