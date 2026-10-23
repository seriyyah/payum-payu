<?php

declare(strict_types=1);

namespace PayumPayU\Tests\Http;

use PayumPayU\Http\ServerHeaderProvider;
use PayumPayU\Tests\TestCase;

final class ServerHeaderProviderTest extends TestCase
{
    public function testTranslatesHeaderNamesToServerKeys(): void
    {
        $provider = new ServerHeaderProvider(['HTTP_OPENPAYU_SIGNATURE' => 'sender=checkout;signature=abc', 'HTTP_X_FORWARDED_FOR' => 1]);

        self::assertSame('sender=checkout;signature=abc', $provider->header('OpenPayu-Signature'));
        self::assertSame('sender=checkout;signature=abc', $provider->header('openpayu-signature'));
        self::assertNull($provider->header('X-Forwarded-For'));
        self::assertNull($provider->header('Missing'));
    }

    public function testReadsFromTheServerGlobals(): void
    {
        $_SERVER['HTTP_X_PAYUM_PAYU_TEST'] = 'present';

        try {
            self::assertSame('present', ServerHeaderProvider::fromGlobals()->header('X-Payum-PayU-Test'));
        } finally {
            unset($_SERVER['HTTP_X_PAYUM_PAYU_TEST']);
        }
    }
}
