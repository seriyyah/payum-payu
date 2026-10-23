<?php

declare(strict_types=1);

namespace PayumPayU\Rest;

use PayumPayU\Rest\Response\OrderCreated;
use PayumPayU\Rest\Response\OrderRetrieved;
use PayumPayU\Rest\Response\PayMethods;
use PayumPayU\Rest\Response\RefundCreated;

/**
 * The subset of the PayU REST API 2.1 this gateway needs.
 *
 * Every method throws {@see Exception\TransportException} when the request
 * cannot be delivered, {@see Exception\ApiException} when PayU rejects it and
 * {@see Exception\InvalidResponseException} when the body is not understood.
 */
interface ClientInterface
{
    /**
     * @param array<string, mixed> $order
     */
    public function createOrder(array $order): OrderCreated;

    public function retrieveOrder(string $orderId): OrderRetrieved;

    public function cancelOrder(string $orderId): void;

    public function createRefund(string $orderId, string $description, ?int $amount): RefundCreated;

    public function retrievePayMethods(string $buyerEmail, string $extCustomerId): PayMethods;
}
