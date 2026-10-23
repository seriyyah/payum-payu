<?php

declare(strict_types=1);

namespace PayumPayU\Model\Exception;

use PayumPayU\Exception\LogicException;

final class MissingDetailException extends LogicException
{
    public static function forKey(string $key, string $hint): self
    {
        return new self(sprintf('The payment detail "%s" is required: %s', $key, $hint));
    }
}
