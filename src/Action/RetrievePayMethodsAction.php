<?php

declare(strict_types=1);

namespace PayumPayU\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use PayumPayU\Request\RetrievePayMethods;

final class RetrievePayMethodsAction implements ActionInterface, ApiAwareInterface
{
    use ApiAwareTrait;

    public function execute(mixed $request): void
    {
        if (! $request instanceof RetrievePayMethods) {
            throw RequestNotSupportedException::createActionNotSupported($this, $request);
        }

        $request->setResult($this->api()->client->retrievePayMethods($request->buyerEmail, $request->extCustomerId));
    }

    public function supports(mixed $request): bool
    {
        return $request instanceof RetrievePayMethods;
    }
}
