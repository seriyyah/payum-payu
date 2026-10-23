<?php

declare(strict_types=1);

namespace PayumPayU\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use PayumPayU\Request\RefundOrder;

/**
 * Refunds the whole order, or the amount given in the refundAmount detail.
 */
final class RefundOrderAction implements ActionInterface, ApiAwareInterface
{
    use ApiAwareTrait;
    use ModelRequestTrait;

    private const DEFAULT_DESCRIPTION = 'Refund';

    public function execute(mixed $request): void
    {
        $details = $this->detailsOf($request, RefundOrder::class);

        $refund = $this->api()->client->createRefund(
            $details->requireOrderId(),
            $details->refundDescription() ?? self::DEFAULT_DESCRIPTION,
            $details->refundAmount(),
        );

        $details->setRefund($refund->refund);
    }

    public function supports(mixed $request): bool
    {
        return $this->modelOf($request, RefundOrder::class) !== null;
    }
}
