<?php

declare(strict_types=1);

namespace PayumPayU\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\Request\Sync;

/**
 * Pulls the current order state from PayU. A payment without an order yet
 * has nothing to synchronise.
 */
final class SyncAction implements ActionInterface, ApiAwareInterface
{
    use ApiAwareTrait;
    use ModelRequestTrait;

    public function execute(mixed $request): void
    {
        $details = $this->detailsOf($request, Sync::class);
        $orderId = $details->orderId();

        if ($orderId === null) {
            return;
        }

        $order = $this->api()->client->retrieveOrder($orderId);
        $details->setStatus($order->status);

        if ($order->payMethodType === null) {
            return;
        }

        $details->setPayMethodType($order->payMethodType);
    }

    public function supports(mixed $request): bool
    {
        return $this->modelOf($request, Sync::class) !== null;
    }
}
