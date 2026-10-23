<?php

declare(strict_types=1);

namespace PayumPayU\Rest\Response;

use PayumPayU\Json\Document;

final readonly class OrderCreated
{
    public function __construct(
        public Status $status,
        public string $orderId,
        public ?string $extOrderId,
        public ?string $redirectUri,
        public ?string $cardMaskedNumber,
    ) {
    }

    public static function fromDocument(Document $document): self
    {
        return new self(
            Status::fromDocument($document->document('status')),
            $document->string('orderId'),
            $document->optionalString('extOrderId'),
            $document->optionalString('redirectUri'),
            $document->optionalDocument('payMethods')?->optionalDocument('payMethod')?->optionalDocument('card')?->optionalString('number'),
        );
    }
}
