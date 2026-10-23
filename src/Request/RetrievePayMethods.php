<?php

declare(strict_types=1);

namespace PayumPayU\Request;

use PayumPayU\Exception\LogicException;
use PayumPayU\Rest\Response\PayMethods;

/**
 * Lists the pay methods, including stored card tokens, PayU holds for a buyer.
 */
final class RetrievePayMethods
{
    private ?PayMethods $result = null;

    public function __construct(
        public readonly string $buyerEmail,
        public readonly string $extCustomerId,
    ) {
    }

    public function result(): PayMethods
    {
        return $this->result ?? throw new LogicException('The request has not been executed by the gateway yet.');
    }

    public function setResult(PayMethods $result): void
    {
        $this->result = $result;
    }
}
