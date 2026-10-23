<?php

declare(strict_types=1);

namespace PayumPayU\Recurring\Exception;

use PayumPayU\Exception\RuntimeException;

final class CardTokenNotFoundException extends RuntimeException
{
    public static function forBuyer(?string $cardNumberMasked): self
    {
        return new self(
            $cardNumberMasked === null
                ? 'The buyer has no active card token stored at PayU.'
                : sprintf('The buyer has no active card token stored at PayU for the card %s.', $cardNumberMasked),
        );
    }
}
