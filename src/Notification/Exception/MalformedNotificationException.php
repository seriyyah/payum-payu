<?php

declare(strict_types=1);

namespace PayumPayU\Notification\Exception;

use PayumPayU\Exception\RuntimeException;
use PayumPayU\Json\Exception\DocumentException;

final class MalformedNotificationException extends RuntimeException
{
    public static function fromDocumentException(DocumentException $exception): self
    {
        return new self('The PayU notification cannot be read: ' . $exception->getMessage(), previous: $exception);
    }

    public static function unknownShape(): self
    {
        return new self('The PayU notification is neither an order nor a refund notification.');
    }

    public static function unknownStatus(string $kind, string $status): self
    {
        return new self(sprintf('The PayU notification carries an unknown %s status "%s".', $kind, $status));
    }
}
