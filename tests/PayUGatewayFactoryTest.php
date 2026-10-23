<?php

declare(strict_types=1);

namespace PayumPayU\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\LogicException as PayumLogicException;
use Payum\Core\GatewayInterface;
use Payum\Core\HttpClientInterface;
use PayumPayU\Action\NotifyAction;
use PayumPayU\Exception\InvalidArgumentException;
use PayumPayU\Exception\LogicException;
use PayumPayU\PayUGatewayFactory;
use PayumPayU\Rest\Environment;
use PHPUnit\Framework\Attributes\DataProvider;

final class PayUGatewayFactoryTest extends TestCase
{
    public function testDescribesItselfAndItsOptions(): void
    {
        $config = (new PayUGatewayFactory())->createConfig();

        self::assertSame('payu', $config['payum.factory_name']);
        self::assertSame('PayU', $config['payum.factory_title']);
        self::assertSame([
            'environment' => 'secure',
            'pos_id' => '',
            'signature_key' => '',
            'oauth_client_id' => '',
            'oauth_client_secret' => '',
        ], $config['payum.default_options']);
        self::assertSame(['environment', 'pos_id', 'signature_key', 'oauth_client_id', 'oauth_client_secret'], $config['payum.required_options']);
        self::assertSame('secure', $config['environment']);
        self::assertSame('', $config['pos_id']);
        self::assertInstanceOf(\Closure::class, $config['payum.api']);
        self::assertInstanceOf(\Closure::class, $config['payum.action.notify']);
        self::assertInstanceOf(\Closure::class, $config[PayUGatewayFactory::CONFIG_HTTP_CLIENT]);
    }

    public function testBuildsAGatewayWithDiscoveredDefaults(): void
    {
        $gateway = (new PayUGatewayFactory())->create(self::options());

        self::assertInstanceOf(GatewayInterface::class, $gateway);
    }

    public function testResolvesTheNotifyActionWithTheConfiguredHeaderProvider(): void
    {
        $config = (new PayUGatewayFactory())->createConfig();
        $notify = $config['payum.action.notify'];

        self::assertInstanceOf(\Closure::class, $notify);
        self::assertInstanceOf(NotifyAction::class, $notify(new ArrayObject($config)));
    }

    public function testLeavesACustomApiAlone(): void
    {
        $api = new \stdClass();

        $config = (new PayUGatewayFactory())->createConfig(['payum.api' => $api]);

        self::assertSame($api, $config['payum.api']);
        self::assertArrayNotHasKey('pos_id', $config);
        self::assertArrayNotHasKey('signature_key', $config);
    }

    public function testRequiresEveryCredential(): void
    {
        $options = self::options();
        unset($options['signature_key']);

        self::assertThrows(PayumLogicException::class, static fn () => (new PayUGatewayFactory())->create($options), 'signature_key');
    }

    public function testRejectsAnUnknownEnvironment(): void
    {
        self::assertThrows(
            InvalidArgumentException::class,
            static fn () => (new PayUGatewayFactory())->create(['environment' => 'staging'] + self::options()),
            'The option "environment" must be "secure" or "sandbox", got "staging".',
        );
    }

    #[DataProvider('nonStringOptions')]
    public function testRejectsNonStringOptions(string $option, mixed $value): void
    {
        self::assertThrows(
            InvalidArgumentException::class,
            static fn () => (new PayUGatewayFactory())->create([$option => $value] + self::options()),
            sprintf('The option "%s" must be a string.', $option),
        );
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function nonStringOptions(): iterable
    {
        yield 'environment enum' => ['environment', Environment::Sandbox];
        yield 'numeric pos id' => ['pos_id', 300746];
    }

    public function testRejectsServicesOfTheWrongType(): void
    {
        self::assertThrows(
            LogicException::class,
            static fn () => (new PayUGatewayFactory())->create(self::options() + [PayUGatewayFactory::CONFIG_CLOCK => new \stdClass()]),
            'The config entry "payum.payu.clock" must be an instance of Psr\Clock\ClockInterface.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function options(): array
    {
        return [
            'payum.http_client' => self::createStub(HttpClientInterface::class),
            'httplug.client' => self::createStub(HttpClientInterface::class),
            'httplug.message_factory' => new Psr17Factory(),
            'httplug.stream_factory' => new Psr17Factory(),
            'environment' => 'sandbox',
            'pos_id' => '300746',
            'signature_key' => 'second-key',
            'oauth_client_id' => 'client-id',
            'oauth_client_secret' => 'client-secret',
        ];
    }
}
