<?php

declare(strict_types=1);

namespace PayumPayU\Tests\Rest\OAuth;

use PayumPayU\Rest\OAuth\Grant;
use PayumPayU\Tests\TestCase;

final class GrantTest extends TestCase
{
    public function testClientCredentials(): void
    {
        $grant = Grant::clientCredentials();

        self::assertSame('client_credentials::', $grant->cacheKey());
        self::assertSame(
            ['grant_type' => 'client_credentials', 'client_id' => 'id', 'client_secret' => 'secret'],
            $grant->toFormFields('id', 'secret'),
        );
    }

    public function testTrustedMerchant(): void
    {
        $grant = Grant::trustedMerchant('buyer@example.com', 'C-1');

        self::assertSame('trusted_merchant:buyer@example.com:C-1', $grant->cacheKey());
        self::assertSame(
            [
                'grant_type' => 'trusted_merchant',
                'client_id' => 'id',
                'client_secret' => 'secret',
                'email' => 'buyer@example.com',
                'ext_customer_id' => 'C-1',
            ],
            $grant->toFormFields('id', 'secret'),
        );
    }
}
