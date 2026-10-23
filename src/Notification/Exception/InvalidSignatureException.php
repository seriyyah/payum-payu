<?php

declare(strict_types=1);

namespace PayumPayU\Notification\Exception;

use PayumPayU\Exception\RuntimeException;

final class InvalidSignatureException extends RuntimeException
{
    public static function missingHeader(): self
    {
        return new self('The notification carries no OpenPayu-Signature header.');
    }

    public static function malformedHeader(string $header): self
    {
        return new self(sprintf('The OpenPayu-Signature header "%s" is malformed.', $header));
    }

    public static function unsupportedAlgorithm(string $algorithm): self
    {
        return new self(sprintf('The signature algorithm "%s" is not supported.', $algorithm));
    }

    public static function mismatch(): self
    {
        return new self('The notification signature does not match the second key of this point of sale.');
    }
}
