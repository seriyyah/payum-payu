<?php

declare(strict_types=1);

namespace PayumPayU\Action;

use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Generic;
use PayumPayU\Model\Details;

/**
 * Shared support check for actions that work on a Payum request whose model
 * is the payment details array.
 */
trait ModelRequestTrait
{
    /**
     * @param class-string<Generic> $requestClass
     *
     * @return \ArrayAccess<string, mixed>|null
     */
    private function modelOf(mixed $request, string $requestClass): ?\ArrayAccess
    {
        if (! $request instanceof $requestClass) {
            return null;
        }

        $model = $request->getModel();

        return $model instanceof \ArrayAccess ? $model : null;
    }

    /**
     * @param class-string<Generic> $requestClass
     */
    private function detailsOf(mixed $request, string $requestClass): Details
    {
        $model = $this->modelOf($request, $requestClass)
            ?? throw RequestNotSupportedException::createActionNotSupported($this, $request);

        return new Details($model);
    }
}
