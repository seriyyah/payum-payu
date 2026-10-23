<?php

declare(strict_types=1);

namespace PayumPayU\Notification;

use PayumPayU\Notification\Exception\InvalidSignatureException;

/**
 * Hash algorithms PayU uses to sign notifications. The header spells them
 * with or without a dash (SHA-256 and SHA256), both are accepted.
 */
enum Algorithm: string
{
    case Md5 = 'md5';
    case Sha1 = 'sha1';
    case Sha256 = 'sha256';

    public static function fromHeaderValue(string $value): self
    {
        return match (strtolower(str_replace('-', '', $value))) {
            'md5' => self::Md5,
            'sha1' => self::Sha1,
            'sha256' => self::Sha256,
            default => throw InvalidSignatureException::unsupportedAlgorithm($value),
        };
    }

    public function hash(string $data): string
    {
        return hash($this->value, $data);
    }
}
