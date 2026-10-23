<?php

declare(strict_types=1);

namespace PayumPayU\Rest\Response;

use PayumPayU\Json\Document;

/**
 * A card stored at PayU for a buyer, usable for merchant-initiated charges.
 */
final readonly class CardToken
{
    private const STATUS_ACTIVE = 'ACTIVE';

    public function __construct(
        public string $value,
        public string $status,
        public bool $preferred,
        public string $cardNumberMasked,
        public ?string $cardBrand,
        public ?string $cardExpirationMonth,
        public ?string $cardExpirationYear,
    ) {
    }

    public static function fromDocument(Document $document): self
    {
        return new self(
            $document->string('value'),
            $document->string('status'),
            $document->has('preferred') && $document->bool('preferred'),
            $document->string('cardNumberMasked'),
            $document->optionalString('cardBrand'),
            $document->optionalString('cardExpirationMonth'),
            $document->optionalString('cardExpirationYear'),
        );
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
