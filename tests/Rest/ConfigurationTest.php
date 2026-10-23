<?php

declare(strict_types=1);

namespace PayumPayU\Tests\Rest;

use PayumPayU\Exception\InvalidArgumentException;
use PayumPayU\Rest\Configuration;
use PayumPayU\Rest\Environment;
use PayumPayU\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ConfigurationTest extends TestCase
{
    public function testExposesCredentials(): void
    {
        $configuration = new Configuration(Environment::Sandbox, '300746', 'b6ca15b0d1020e8094d9b5f8d163db54', '300746', 'secret');

        self::assertSame(Environment::Sandbox, $configuration->environment);
        self::assertSame('300746', $configuration->posId);
        self::assertSame('b6ca15b0d1020e8094d9b5f8d163db54', $configuration->signatureKey);
        self::assertSame('300746', $configuration->oauthClientId);
        self::assertSame('secret', $configuration->oauthClientSecret);
    }

    #[DataProvider('blankFields')]
    public function testRejectsBlankCredentials(string $posId, string $signatureKey, string $clientId, string $secret, string $field): void
    {
        self::assertThrows(
            InvalidArgumentException::class,
            static fn (): Configuration => new Configuration(Environment::Production, $posId, $signatureKey, $clientId, $secret),
            sprintf('The PayU configuration value "%s" must not be empty.', $field),
        );
    }

    /**
     * @return iterable<string, array{string, string, string, string, string}>
     */
    public static function blankFields(): iterable
    {
        yield 'pos id' => ['', 'k', 'c', 's', 'posId'];
        yield 'signature key' => ['p', '', 'c', 's', 'signatureKey'];
        yield 'client id' => ['p', 'k', '', 's', 'oauthClientId'];
        yield 'client secret' => ['p', 'k', 'c', '', 'oauthClientSecret'];
    }
}
