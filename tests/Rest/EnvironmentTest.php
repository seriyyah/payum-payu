<?php

declare(strict_types=1);

namespace PayumPayU\Tests\Rest;

use PayumPayU\Rest\Environment;
use PayumPayU\Tests\TestCase;

final class EnvironmentTest extends TestCase
{
    public function testBaseUris(): void
    {
        self::assertSame('https://secure.payu.com', Environment::Production->baseUri());
        self::assertSame('https://secure.snd.payu.com', Environment::Sandbox->baseUri());
    }

    public function testOptionValuesMatchTheMerchantPanelWording(): void
    {
        self::assertSame(Environment::Production, Environment::from('secure'));
        self::assertSame(Environment::Sandbox, Environment::from('sandbox'));
    }
}
