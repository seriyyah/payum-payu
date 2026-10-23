<?php

declare(strict_types=1);

namespace PayumPayU\Notification;

use PayumPayU\Notification\Exception\InvalidSignatureException;

/**
 * Parsed OpenPayu-Signature header, for example
 * "sender=checkout;signature=3f2a...;algorithm=SHA-256;content=DOCUMENT".
 */
final readonly class Signature
{
    private const FIELD_SIGNATURE = 'signature';

    private const FIELD_ALGORITHM = 'algorithm';

    public function __construct(
        public string $value,
        public Algorithm $algorithm,
    ) {
    }

    public static function fromHeader(string $header): self
    {
        $fields = [];

        foreach (explode(';', $header) as $pair) {
            $parts = explode('=', trim($pair), 2);

            if (count($parts) !== 2 || $parts[0] === '') {
                continue;
            }

            $fields[strtolower($parts[0])] = $parts[1];
        }

        $value = $fields[self::FIELD_SIGNATURE] ?? '';

        if ($value === '') {
            throw InvalidSignatureException::malformedHeader($header);
        }

        return new self($value, Algorithm::fromHeaderValue($fields[self::FIELD_ALGORITHM] ?? Algorithm::Md5->value));
    }

    public function matches(string $body, string $secondKey): bool
    {
        return hash_equals($this->algorithm->hash($body . $secondKey), strtolower($this->value));
    }
}
