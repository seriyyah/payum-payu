<?php

declare(strict_types=1);

namespace PayumPayU\Action;

use Payum\Core\Security\GenericTokenFactoryInterface;
use PayumPayU\Exception\LogicException;

/**
 * Typed replacement for Payum's GenericTokenFactoryAwareTrait.
 */
trait TokenFactoryAwareTrait
{
    private ?GenericTokenFactoryInterface $tokenFactory = null;

    public function setGenericTokenFactory(?GenericTokenFactoryInterface $genericTokenFactory = null): void
    {
        $this->tokenFactory = $genericTokenFactory;
    }

    private function tokenFactory(): GenericTokenFactoryInterface
    {
        return $this->tokenFactory
            ?? throw new LogicException('The token factory has not been set on the action; register it through the gateway factory.');
    }
}
