<?php

declare(strict_types=1);

namespace PayumPayU;

use PayumPayU\Rest\ClientInterface;
use PayumPayU\Rest\Configuration;

/**
 * What Payum injects into api-aware actions of this gateway.
 */
final readonly class Api
{
    public function __construct(
        public Configuration $configuration,
        public ClientInterface $client,
    ) {
    }
}
