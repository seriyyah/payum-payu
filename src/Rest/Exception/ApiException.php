<?php

declare(strict_types=1);

namespace PayumPayU\Rest\Exception;

use PayumPayU\Exception\RuntimeException;
use PayumPayU\Rest\Response\Status;

/**
 * PayU rejected the request. The PayU status object is kept when the body
 * carried one, so callers can act on codes such as ERROR_VALUE_INVALID.
 */
final class ApiException extends RuntimeException
{
    private function __construct(
        string $message,
        public readonly int $httpStatusCode,
        public readonly ?Status $status,
    ) {
        parent::__construct($message);
    }

    public static function fromStatus(string $operation, int $httpStatusCode, Status $status): self
    {
        return new self(
            sprintf(
                'PayU rejected %s with %s (HTTP %d): %s',
                $operation,
                $status->statusCode,
                $httpStatusCode,
                $status->statusDesc ?? 'no description',
            ),
            $httpStatusCode,
            $status,
        );
    }

    public static function fromHttpStatus(string $operation, int $httpStatusCode): self
    {
        return new self(sprintf('PayU rejected %s with HTTP %d.', $operation, $httpStatusCode), $httpStatusCode, null);
    }
}
