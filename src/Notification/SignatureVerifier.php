<?php

declare(strict_types=1);

namespace PayumPayU\Notification;

use PayumPayU\Notification\Exception\InvalidSignatureException;

final class SignatureVerifier
{
    /**
     * @throws InvalidSignatureException
     */
    public function verify(string $body, ?string $header, string $secondKey): void
    {
        if ($header === null || trim($header) === '') {
            throw InvalidSignatureException::missingHeader();
        }

        if (! Signature::fromHeader($header)->matches($body, $secondKey)) {
            throw InvalidSignatureException::mismatch();
        }
    }
}
