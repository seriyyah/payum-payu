<?php

declare(strict_types=1);

namespace PayumPayU\Rest\Response;

use PayumPayU\Json\Document;
use PayumPayU\Json\Exception\DocumentException;
use PayumPayU\Model\RefundDetails;
use PayumPayU\Model\RefundStatus;

final readonly class RefundCreated
{
    public function __construct(
        public Status $status,
        public string $orderId,
        public RefundDetails $refund,
    ) {
    }

    public static function fromDocument(Document $document): self
    {
        $refund = $document->document('refund');
        $refundStatus = $refund->string('status');

        return new self(
            Status::fromDocument($document->document('status')),
            $document->string('orderId'),
            new RefundDetails(
                $refund->string('refundId'),
                RefundStatus::tryFrom($refundStatus) ?? throw DocumentException::unexpectedType(
                    '$.refund.status',
                    'a known refund status, got "' . $refundStatus . '"',
                ),
                $refund->optionalInt('amount'),
                $refund->optionalString('currencyCode'),
                $refund->optionalString('description'),
            ),
        );
    }
}
