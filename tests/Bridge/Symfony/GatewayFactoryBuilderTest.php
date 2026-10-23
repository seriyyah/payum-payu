<?php

declare(strict_types=1);

namespace PayumPayU\Tests\Bridge\Symfony;

use Payum\Core\CoreGatewayFactory;
use PayumPayU\Bridge\Symfony\GatewayFactoryBuilder;
use PayumPayU\PayUGatewayFactory;
use PayumPayU\Tests\Double\ArrayHeaderProvider;
use PayumPayU\Tests\Double\RecordingHttpClient;
use PayumPayU\Tests\TestCase;

final class GatewayFactoryBuilderTest extends TestCase
{
    public function testInjectsTheContainerServicesIntoTheFactoryDefaults(): void
    {
        $headerProvider = new ArrayHeaderProvider([]);
        $httpClient = new RecordingHttpClient();
        $builder = new GatewayFactoryBuilder($headerProvider, $httpClient);

        $factory = $builder(['payum.template.layout' => '@PayumCore\layout.html.twig'], new CoreGatewayFactory());
        $config = $factory->createConfig();

        self::assertSame($headerProvider, $config[PayUGatewayFactory::CONFIG_HEADER_PROVIDER]);
        self::assertSame($httpClient, $config[PayUGatewayFactory::CONFIG_HTTP_CLIENT]);
        self::assertSame('@PayumCore\layout.html.twig', $config['payum.template.layout']);
    }

    public function testLeavesHttpClientDiscoveryInPlaceWhenNoneIsConfigured(): void
    {
        $builder = new GatewayFactoryBuilder(new ArrayHeaderProvider([]));

        $config = $builder([], new CoreGatewayFactory())->createConfig();

        self::assertInstanceOf(\Closure::class, $config[PayUGatewayFactory::CONFIG_HTTP_CLIENT]);
    }
}
