<?php

declare(strict_types=1);

namespace PayumPayU\Tests\Bridge\Symfony;

use Payum\Core\CoreGatewayFactory;
use PayumPayU\Bridge\Symfony\GatewayFactoryBuilder;
use PayumPayU\Bridge\Symfony\PayumPayUBundle;
use PayumPayU\Bridge\Symfony\RequestStackHeaderProvider;
use PayumPayU\PayUGatewayFactory;
use PayumPayU\Tests\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\HttpFoundation\RequestStack;

final class PayumPayUBundleTest extends TestCase
{
    public function testRegistersTheGatewayFactoryBuilderForPayumBundle(): void
    {
        $container = $this->load(new PayumPayUBundle());

        $builder = $container->getDefinition(PayumPayUBundle::SERVICE_GATEWAY_FACTORY_BUILDER);
        self::assertSame(GatewayFactoryBuilder::class, $builder->getClass());
        self::assertSame([['factory' => 'payu']], $builder->getTag('payum.gateway_factory_builder'));
        self::assertEquals(new Reference(PayumPayUBundle::SERVICE_HEADER_PROVIDER), $builder->getArgument(0));
        self::assertEquals(new Reference(PayumPayUBundle::SERVICE_HTTP_CLIENT), $builder->getArgument(1));
        self::assertSame(RequestStackHeaderProvider::class, $container->getDefinition(PayumPayUBundle::SERVICE_HEADER_PROVIDER)->getClass());
        $psr18 = $container->getDefinition(PayumPayUBundle::SERVICE_HTTP_CLIENT);
        self::assertSame(Psr18Client::class, $psr18->getClass());
        self::assertEquals([new Reference('payum_payu.http_client.symfony')], $psr18->getArguments());
        $symfonyClient = $container->getDefinition('payum_payu.http_client.symfony');
        self::assertSame([HttpClient::class, 'create'], $symfonyClient->getFactory());
        self::assertSame([['max_redirects' => 0]], $symfonyClient->getArguments());
    }

    public function testTheWiredHttpClientDoesNotFollowRedirects(): void
    {
        $container = $this->load(new PayumPayUBundle());
        $container->getDefinition(PayumPayUBundle::SERVICE_GATEWAY_FACTORY_BUILDER)->setPublic(true);
        $container->compile();

        $builder = $container->get(PayumPayUBundle::SERVICE_GATEWAY_FACTORY_BUILDER);
        self::assertInstanceOf(GatewayFactoryBuilder::class, $builder);
        $config = $builder([], new CoreGatewayFactory())->createConfig();

        self::assertInstanceOf(Psr18Client::class, $config[PayUGatewayFactory::CONFIG_HTTP_CLIENT]);
        self::assertInstanceOf(RequestStackHeaderProvider::class, $config[PayUGatewayFactory::CONFIG_HEADER_PROVIDER]);
    }

    public function testTheSymfonyHttpClientCanBeLeftOut(): void
    {
        $container = $this->load(new PayumPayUBundle(wireSymfonyHttpClient: false));

        self::assertFalse($container->hasDefinition(PayumPayUBundle::SERVICE_HTTP_CLIENT));
        self::assertNull($container->getDefinition(PayumPayUBundle::SERVICE_GATEWAY_FACTORY_BUILDER)->getArgument(1));
    }

    private function load(PayumPayUBundle $bundle): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register('request_stack', RequestStack::class);
        $extension = $bundle->getContainerExtension();
        self::assertNotNull($extension);
        $extension->load([], $container);

        return $container;
    }
}
