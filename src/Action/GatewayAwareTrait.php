<?php

declare(strict_types=1);

namespace PayumPayU\Action;

use Payum\Core\GatewayInterface;
use PayumPayU\Exception\LogicException;

/**
 * Typed replacement for Payum's GatewayAwareTrait.
 */
trait GatewayAwareTrait
{
    private ?GatewayInterface $gateway = null;

    public function setGateway(GatewayInterface $gateway): void
    {
        $this->gateway = $gateway;
    }

    private function gateway(): GatewayInterface
    {
        return $this->gateway
            ?? throw new LogicException('The gateway has not been set on the action; register it through the gateway factory.');
    }
}
