<?php

declare(strict_types=1);

namespace PayumPayU\Bridge\Symfony;

use Payum\Core\GatewayFactoryInterface;
use PayumPayU\Http\HeaderProviderInterface;
use PayumPayU\PayUGatewayFactory;
use Psr\Http\Client\ClientInterface;

/**
 * Invoked by PayumBundle for services tagged payum.gateway_factory_builder.
 * Injects the container-provided header provider and HTTP client into the
 * gateway factory defaults.
 */
final readonly class GatewayFactoryBuilder
{
    public function __construct(
        private HeaderProviderInterface $headerProvider,
        private ?ClientInterface $httpClient = null,
    ) {
    }

    /**
     * @param array<string, mixed> $defaultConfig
     */
    public function __invoke(array $defaultConfig, GatewayFactoryInterface $coreGatewayFactory): PayUGatewayFactory
    {
        $defaultConfig[PayUGatewayFactory::CONFIG_HEADER_PROVIDER] = $this->headerProvider;

        if ($this->httpClient !== null) {
            $defaultConfig[PayUGatewayFactory::CONFIG_HTTP_CLIENT] = $this->httpClient;
        }

        return new PayUGatewayFactory($defaultConfig, $coreGatewayFactory);
    }
}
