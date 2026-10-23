<?php

declare(strict_types=1);

namespace PayumPayU\Model;

/**
 * Refund state stored in the payment details.
 */
final readonly class RefundDetails
{
    public function __construct(
        public string $refundId,
        public RefundStatus $status,
        public ?int $amount,
        public ?string $currencyCode,
        public ?string $description,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): ?self
    {
        $refundId = $data['refundId'] ?? null;
        $status = $data['status'] ?? null;

        if (! is_string($refundId) || ! is_string($status)) {
            return null;
        }

        $refundStatus = RefundStatus::tryFrom($status);

        if ($refundStatus === null) {
            return null;
        }

        $amount = $data['amount'] ?? null;
        $currencyCode = $data['currencyCode'] ?? null;
        $description = $data['description'] ?? null;

        return new self(
            $refundId,
            $refundStatus,
            is_int($amount) ? $amount : null,
            is_string($currencyCode) ? $currencyCode : null,
            is_string($description) ? $description : null,
        );
    }

    /**
     * @return array{refundId: string, status: string, amount: int|null, currencyCode: string|null, description: string|null}
     */
    public function toArray(): array
    {
        return [
            'refundId' => $this->refundId,
            'status' => $this->status->value,
            'amount' => $this->amount,
            'currencyCode' => $this->currencyCode,
            'description' => $this->description,
        ];
    }
}
