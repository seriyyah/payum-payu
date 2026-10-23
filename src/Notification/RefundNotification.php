<?php

declare(strict_types=1);

namespace PayumPayU\Notification;

use PayumPayU\Model\RefundDetails;

final readonly class RefundNotification
{
    public function __construct(
        public string $orderId,
        public ?string $extOrderId,
        public RefundDetails $refund,
    ) {
    }
}
