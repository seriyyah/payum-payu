<?php

declare(strict_types=1);

namespace PayumPayU\Rest\Response;

use PayumPayU\Json\Document;
use PayumPayU\Json\Exception\DocumentException;
use PayumPayU\Model\OrderStatus;

final readonly class OrderRetrieved
{
    public function __construct(
        public string $orderId,
        public OrderStatus $status,
        public ?string $payMethodType,
        public ?int $totalAmount,
        public ?string $currencyCode,
    ) {
    }

    public static function fromDocument(Document $document): self
    {
        $orders = $document->documents('orders');
        $order = $orders[0] ?? throw DocumentException::missingField('$.orders[0]');
        $status = $order->string('status');

        return new self(
            $order->string('orderId'),
            OrderStatus::tryFrom($status)
                ?? throw DocumentException::unexpectedType('$.orders[0].status', 'a known order status, got "' . $status . '"'),
            $order->optionalDocument('payMethod')?->optionalString('type'),
            $order->optionalInt('totalAmount'),
            $order->optionalString('currencyCode'),
        );
    }
}
