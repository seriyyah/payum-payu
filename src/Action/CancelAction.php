<?php

declare(strict_types=1);

namespace PayumPayU\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\Request\Cancel;
use PayumPayU\Model\OrderStatus;

final class CancelAction implements ActionInterface, ApiAwareInterface
{
    use ApiAwareTrait;
    use ModelRequestTrait;

    public function execute(mixed $request): void
    {
        $details = $this->detailsOf($request, Cancel::class);

        $this->api()->client->cancelOrder($details->requireOrderId());
        $details->setStatus(OrderStatus::Canceled);
    }

    public function supports(mixed $request): bool
    {
        return $this->modelOf($request, Cancel::class) !== null;
    }
}
