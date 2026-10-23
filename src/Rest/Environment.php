<?php

declare(strict_types=1);

namespace PayumPayU\Rest;

enum Environment: string
{
    case Production = 'secure';
    case Sandbox = 'sandbox';

    public function baseUri(): string
    {
        return match ($this) {
            self::Production => 'https://secure.payu.com',
            self::Sandbox => 'https://secure.snd.payu.com',
        };
    }
}
