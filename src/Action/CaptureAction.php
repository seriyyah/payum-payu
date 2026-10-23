<?php

declare(strict_types=1);

namespace PayumPayU\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\Request\Capture;
use Payum\Core\Request\Sync;
use PayumPayU\Request\CreateOrder;

/**
 * First run: creates the PayU order and redirects the buyer. Second run,
 * after the buyer returns to the continue URL: fetches the order state.
 */
final class CaptureAction implements ActionInterface, GatewayAwareInterface
{
    use GatewayAwareTrait;
    use ModelRequestTrait;

    public function execute(mixed $request): void
    {
        $details = $this->detailsOf($request, Capture::class);
        /** @var Capture $request */
        $next = $details->orderId() === null ? new CreateOrder($request->getToken()) : new Sync($request->getToken());
        $next->setModel($request->getFirstModel());
        $next->setModel($request->getModel());

        $this->gateway()->execute($next);
    }

    public function supports(mixed $request): bool
    {
        return $this->modelOf($request, Capture::class) !== null;
    }
}
