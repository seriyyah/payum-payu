<?php

declare(strict_types=1);

namespace PayumPayU\Tests\Double;

use Payum\Core\Security\TokenFactoryInterface;
use Payum\Core\Security\TokenInterface;

/**
 * Hands out the same token for every request; tests only need its URLs.
 */
final readonly class FixedTokenFactory implements TokenFactoryInterface
{
    public function __construct(
        private TokenInterface $token,
    ) {
    }

    /**
     * @param array<mixed> $targetParameters
     * @param array<mixed> $afterParameters
     */
    public function createToken(
        mixed $gatewayName,
        mixed $model,
        mixed $targetPath,
        array $targetParameters = [],
        mixed $afterPath = null,
        array $afterParameters = [],
    ): TokenInterface {
        return $this->token;
    }
}
