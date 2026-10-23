<?php

declare(strict_types=1);

namespace PayumPayU\Tests\Notification;

use PayumPayU\Notification\Exception\InvalidSignatureException;
use PayumPayU\Notification\SignatureVerifier;
use PayumPayU\Tests\TestCase;

final class SignatureVerifierTest extends TestCase
{
    private const BODY = '{"order":{"orderId":"O1"}}';
    private const SECOND_KEY = 'second-key';

    public function testAcceptsAValidSignature(): void
    {
        $this->expectNotToPerformAssertions();

        (new SignatureVerifier())->verify(self::BODY, 'signature=' . md5(self::BODY . self::SECOND_KEY) . ';algorithm=MD5', self::SECOND_KEY);
    }

    public function testRejectsAMissingHeader(): void
    {
        self::assertThrows(
            InvalidSignatureException::class,
            static fn () => (new SignatureVerifier())->verify(self::BODY, null, self::SECOND_KEY),
            'The notification carries no OpenPayu-Signature header.',
        );
    }

    public function testRejectsABlankHeader(): void
    {
        self::assertThrows(
            InvalidSignatureException::class,
            static fn () => (new SignatureVerifier())->verify(self::BODY, '  ', self::SECOND_KEY),
            'The notification carries no OpenPayu-Signature header.',
        );
    }

    public function testRejectsAWrongSignature(): void
    {
        self::assertThrows(
            InvalidSignatureException::class,
            static fn () => (new SignatureVerifier())->verify(self::BODY, 'signature=' . md5('tampered') . ';algorithm=MD5', self::SECOND_KEY),
            'The notification signature does not match the second key of this point of sale.',
        );
    }
}
