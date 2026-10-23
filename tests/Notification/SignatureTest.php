<?php

declare(strict_types=1);

namespace PayumPayU\Tests\Notification;

use PayumPayU\Notification\Algorithm;
use PayumPayU\Notification\Exception\InvalidSignatureException;
use PayumPayU\Notification\Signature;
use PayumPayU\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class SignatureTest extends TestCase
{
    public function testParsesThePayUHeader(): void
    {
        $signature = Signature::fromHeader('sender=checkout;signature=ABCDEF;algorithm=SHA-256;content=DOCUMENT');

        self::assertSame('ABCDEF', $signature->value);
        self::assertSame(Algorithm::Sha256, $signature->algorithm);
    }

    public function testToleratesSpacesCaseAndMissingAlgorithm(): void
    {
        $signature = Signature::fromHeader(' Sender=checkout ; Signature=abc ; content=DOCUMENT');

        self::assertSame('abc', $signature->value);
        self::assertSame(Algorithm::Md5, $signature->algorithm);
    }

    public function testSkipsFieldsWithoutANameOrValueSeparator(): void
    {
        $signature = Signature::fromHeader('junk;=nameless;signature=abc;algorithm=SHA-1');

        self::assertSame('abc', $signature->value);
        self::assertSame(Algorithm::Sha1, $signature->algorithm);
    }

    public function testAnEmptyAlgorithmIsRejected(): void
    {
        self::assertThrows(
            InvalidSignatureException::class,
            static fn (): Signature => Signature::fromHeader('signature=abc;algorithm='),
            'The signature algorithm "" is not supported.',
        );
    }

    public function testKeepsEqualsSignsInsideValues(): void
    {
        self::assertSame('a==', Signature::fromHeader('signature=a==;algorithm=MD5')->value);
    }

    #[DataProvider('malformedHeaders')]
    public function testRejectsHeadersWithoutASignature(string $header): void
    {
        self::assertThrows(
            InvalidSignatureException::class,
            static fn (): Signature => Signature::fromHeader($header),
            sprintf('The OpenPayu-Signature header "%s" is malformed.', $header),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedHeaders(): iterable
    {
        yield 'no fields' => ['garbage'];
        yield 'empty signature' => ['sender=checkout;signature=;algorithm=MD5'];
        yield 'nameless field' => ['=abc;algorithm=MD5'];
    }

    public function testMatchesTheBodyConcatenatedWithTheSecondKey(): void
    {
        $body = '{"order":{"orderId":"O1","status":"COMPLETED"}}';
        $secondKey = 'b6ca15b0d1020e8094d9b5f8d163db54';
        $signature = Signature::fromHeader('sender=checkout;signature=' . strtoupper(hash('sha256', $body . $secondKey)) . ';algorithm=SHA-256');

        self::assertTrue($signature->matches($body, $secondKey));
        self::assertFalse($signature->matches($body . ' ', $secondKey));
        self::assertFalse($signature->matches($body, 'other-key'));
        self::assertFalse(Signature::fromHeader('signature=00;algorithm=MD5')->matches($body, $secondKey));
    }
}
