<?php

declare(strict_types=1);

namespace PayumPayU\Tests\Rest\OAuth;

use PayumPayU\Json\Document;
use PayumPayU\Rest\OAuth\AccessToken;
use PayumPayU\Tests\TestCase;

final class AccessTokenTest extends TestCase
{
    public function testExpiryIsRelativeToTheIssueTime(): void
    {
        $issuedAt = new \DateTimeImmutable('2026-10-23 12:00:00');
        $token = AccessToken::fromDocument(Document::fromArray(['access_token' => 'abc', 'expires_in' => 3600]), $issuedAt);

        self::assertSame('abc', $token->value);
        self::assertEquals(new \DateTimeImmutable('2026-10-23 13:00:00'), $token->expiresAt);
        self::assertTrue($token->isUsableAt(new \DateTimeImmutable('2026-10-23 12:58:59'), 60));
        self::assertFalse($token->isUsableAt(new \DateTimeImmutable('2026-10-23 12:59:00'), 60));
        self::assertFalse($token->isUsableAt(new \DateTimeImmutable('2026-10-23 13:30:00'), 0));
    }
}
