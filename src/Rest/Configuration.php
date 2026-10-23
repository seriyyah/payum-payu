<?php

declare(strict_types=1);

namespace PayumPayU\Rest;

use PayumPayU\Exception\InvalidArgumentException;

/**
 * Point of sale credentials as shown in the PayU merchant panel.
 */
final readonly class Configuration
{
    public function __construct(
        public Environment $environment,
        public string $posId,
        public string $signatureKey,
        public string $oauthClientId,
        public string $oauthClientSecret,
    ) {
        foreach (['posId', 'signatureKey', 'oauthClientId', 'oauthClientSecret'] as $name) {
            if ($this->{$name} === '') {
                throw new InvalidArgumentException(sprintf('The PayU configuration value "%s" must not be empty.', $name));
            }
        }
    }
}
