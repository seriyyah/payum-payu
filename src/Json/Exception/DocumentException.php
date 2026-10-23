<?php

declare(strict_types=1);

namespace PayumPayU\Json\Exception;

use PayumPayU\Exception\RuntimeException;

final class DocumentException extends RuntimeException
{
    public static function malformedJson(string $reason): self
    {
        return new self(sprintf('The JSON document is malformed: %s.', $reason));
    }

    public static function notAnObject(string $path): self
    {
        return new self(sprintf('Expected a JSON object at "%s".', $path));
    }

    public static function missingField(string $path): self
    {
        return new self(sprintf('The field "%s" is missing.', $path));
    }

    public static function unexpectedType(string $path, string $expected): self
    {
        return new self(sprintf('The field "%s" must be %s.', $path, $expected));
    }
}
