<?php

declare(strict_types=1);

namespace PayumPayU\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\GetStatusInterface;
use PayumPayU\Model\Details;
use PayumPayU\Model\OrderStatus;
use PayumPayU\Model\RefundStatus;

final class StatusAction implements ActionInterface
{
    public function execute(mixed $request): void
    {
        $model = $request instanceof GetStatusInterface ? $request->getModel() : null;

        if (! $model instanceof \ArrayAccess) {
            throw RequestNotSupportedException::createActionNotSupported($this, $request);
        }

        /** @var \ArrayAccess<string, mixed> $model */
        $details = new Details($model);

        if ($details->refund()?->status === RefundStatus::Finalized) {
            $request->markRefunded();

            return;
        }

        $status = $details->status();

        if ($status === null && $details->rawStatus() !== null) {
            $request->markUnknown();

            return;
        }

        match ($status) {
            null, OrderStatus::New => $request->markNew(),
            OrderStatus::Pending, OrderStatus::WaitingForConfirmation => $request->markPending(),
            OrderStatus::Completed => $request->markCaptured(),
            OrderStatus::Canceled => $request->markCanceled(),
        };
    }

    public function supports(mixed $request): bool
    {
        return $request instanceof GetStatusInterface && $request->getModel() instanceof \ArrayAccess;
    }
}
