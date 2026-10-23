<?php

declare(strict_types=1);

namespace PayumPayU\Rest\Response;

use PayumPayU\Json\Document;

/**
 * The "status" object PayU attaches to every response.
 */
final readonly class Status
{
    public const SUCCESS = 'SUCCESS';

    private const CONTINUE_CODES = [
        'WARNING_CONTINUE_3DS',
        'WARNING_CONTINUE_CVV',
        'WARNING_CONTINUE_REDIRECT',
    ];

    public function __construct(
        public string $statusCode,
        public ?string $statusDesc,
        public ?string $codeLiteral,
    ) {
    }

    public static function fromDocument(Document $document): self
    {
        return new self(
            $document->string('statusCode'),
            $document->optionalString('statusDesc'),
            $document->optionalString('codeLiteral'),
        );
    }

    /**
     * True for SUCCESS and for the WARNING_CONTINUE_* codes, which mean the
     * order was accepted and the buyer must be redirected to finish it.
     */
    public function isAccepted(): bool
    {
        return $this->statusCode === self::SUCCESS || in_array($this->statusCode, self::CONTINUE_CODES, true);
    }
}
