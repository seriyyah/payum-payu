<?php

declare(strict_types=1);

namespace PayumPayU\Rest\Exception;

use PayumPayU\Exception\RuntimeException;
use PayumPayU\Json\Exception\DocumentException;

/**
 * PayU answered with a body this package cannot interpret.
 */
final class InvalidResponseException extends RuntimeException
{
    public static function fromDocumentException(DocumentException $exception, string $operation): self
    {
        return new self(sprintf('Unexpected PayU response to %s: %s', $operation, $exception->getMessage()), previous: $exception);
    }
}
