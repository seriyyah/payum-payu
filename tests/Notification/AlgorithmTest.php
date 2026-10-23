<?php

declare(strict_types=1);

namespace PayumPayU\Tests\Notification;

use PayumPayU\Notification\Algorithm;
use PayumPayU\Notification\Exception\InvalidSignatureException;
use PayumPayU\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class AlgorithmTest extends TestCase
{
    #[DataProvider('headerValues')]
    public function testAcceptsPayUSpellings(string $value, Algorithm $expected): void
    {
        self::assertSame($expected, Algorithm::fromHeaderValue($value));
    }

    /**
     * @return iterable<string, array{string, Algorithm}>
     */
    public static function headerValues(): iterable
    {
        yield 'md5' => ['MD5', Algorithm::Md5];
        yield 'lower md5' => ['md5', Algorithm::Md5];
        yield 'sha-1' => ['SHA-1', Algorithm::Sha1];
        yield 'sha1' => ['SHA1', Algorithm::Sha1];
        yield 'sha-256' => ['SHA-256', Algorithm::Sha256];
        yield 'sha256' => ['sha256', Algorithm::Sha256];
    }

    public function testHashes(): void
    {
        self::assertSame(md5('abc'), Algorithm::Md5->hash('abc'));
        self::assertSame(sha1('abc'), Algorithm::Sha1->hash('abc'));
        self::assertSame(hash('sha256', 'abc'), Algorithm::Sha256->hash('abc'));
    }

    public function testRejectsUnknownAlgorithms(): void
    {
        self::assertThrows(
            InvalidSignatureException::class,
            static fn (): Algorithm => Algorithm::fromHeaderValue('CRC32'),
            'The signature algorithm "CRC32" is not supported.',
        );
    }
}
