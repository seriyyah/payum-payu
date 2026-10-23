<?php

declare(strict_types=1);

namespace PayumPayU\Notification;

use PayumPayU\Model\OrderStatus;

final readonly class OrderNotification
{
    public function __construct(
        public string $orderId,
        public ?string $extOrderId,
        public OrderStatus $status,
        public ?string $payMethodType,
    ) {
    }
}
