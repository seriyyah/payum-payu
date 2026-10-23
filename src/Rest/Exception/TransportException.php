<?php

declare(strict_types=1);

namespace PayumPayU\Rest\Exception;

use PayumPayU\Exception\RuntimeException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface;

/**
 * The HTTP client could not deliver the request (network, DNS, TLS, timeout).
 */
final class TransportException extends RuntimeException
{
    public static function fromClientException(ClientExceptionInterface $exception, RequestInterface $request): self
    {
        return new self(
            sprintf('%s %s failed: %s', $request->getMethod(), $request->getUri()->getPath(), $exception->getMessage()),
            previous: $exception,
        );
    }
}
