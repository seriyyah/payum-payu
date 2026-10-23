<?php

declare(strict_types=1);

namespace PayumPayU\Model;

/**
 * The "payMethods.payMethod" object of an order.
 */
final readonly class PayMethod
{
    public const TYPE_CARD_TOKEN = 'CARD_TOKEN';

    public const TYPE_PAY_BY_LINK = 'PBL';

    private function __construct(
        public string $type,
        public string $value,
    ) {
    }

    public static function cardToken(string $token): self
    {
        return new self(self::TYPE_CARD_TOKEN, $token);
    }

    public static function payByLink(string $code): self
    {
        return new self(self::TYPE_PAY_BY_LINK, $code);
    }

    public static function of(string $type, string $value): self
    {
        return new self($type, $value);
    }

    /**
     * @return array{payMethod: array{type: string, value: string}}
     */
    public function toArray(): array
    {
        return [
            'payMethod' => [
                'type' => $this->type,
                'value' => $this->value,
            ],
        ];
    }
}
