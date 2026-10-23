<?php

declare(strict_types=1);

namespace PayumPayU\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\Request\Refund;
use PayumPayU\Request\RefundOrder;

final class RefundAction implements ActionInterface, GatewayAwareInterface
{
    use GatewayAwareTrait;
    use ModelRequestTrait;

    public function execute(mixed $request): void
    {
        $this->detailsOf($request, Refund::class);
        /** @var Refund $request */
        $refundOrder = new RefundOrder($request->getToken());
        $refundOrder->setModel($request->getFirstModel());
        $refundOrder->setModel($request->getModel());

        $this->gateway()->execute($refundOrder);
    }

    public function supports(mixed $request): bool
    {
        return $this->modelOf($request, Refund::class) !== null;
    }
}
