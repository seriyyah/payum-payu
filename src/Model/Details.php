<?php

declare(strict_types=1);

namespace PayumPayU\Model;

use PayumPayU\Model\Exception\MissingDetailException;

/**
 * Typed view over the Payum payment details array.
 *
 * Writes go straight to the wrapped storage so that Payum persists them
 * together with the payment.
 */
final readonly class Details
{
    public const ORDER_ID = 'orderId';

    public const STATUS = 'status';

    public const EXT_ORDER_ID = 'extOrderId';

    public const TOTAL_AMOUNT = 'totalAmount';

    public const CURRENCY_CODE = 'currencyCode';

    public const DESCRIPTION = 'description';

    public const CUSTOMER_IP = 'customerIp';

    public const CLIENT_EMAIL = 'clientEmail';

    public const CLIENT_ID = 'clientId';

    public const FIRST_NAME = 'firstName';

    public const LAST_NAME = 'lastName';

    public const LANGUAGE = 'language';

    public const VALIDITY_TIME = 'validityTime';

    public const RECURRING = 'recurring';

    public const PAY_METHODS = 'payMethods';

    public const PAY_METHOD_TYPE = 'payMethodType';

    public const PRODUCTS = 'products';

    public const REDIRECT_URI = 'redirectUri';

    public const CARD_MASKED_NUMBER = 'cardMaskedNumber';

    public const REFUND = 'refund';

    public const REFUND_AMOUNT = 'refundAmount';

    public const REFUND_DESCRIPTION = 'refundDescription';

    /**
     * @param \ArrayAccess<string, mixed> $storage
     */
    public function __construct(
        private \ArrayAccess $storage,
    ) {
    }

    public function orderId(): ?string
    {
        return $this->string(self::ORDER_ID);
    }

    public function requireOrderId(): string
    {
        return $this->orderId() ?? throw MissingDetailException::forKey(
            self::ORDER_ID,
            'the order has not been created at PayU yet, capture the payment first',
        );
    }

    public function setOrderId(string $orderId): void
    {
        $this->storage[self::ORDER_ID] = $orderId;
    }

    public function status(): ?OrderStatus
    {
        $status = $this->string(self::STATUS);

        return $status === null ? null : OrderStatus::tryFrom($status);
    }

    public function rawStatus(): ?string
    {
        return $this->string(self::STATUS);
    }

    public function setStatus(OrderStatus $status): void
    {
        $this->storage[self::STATUS] = $status->value;
    }

    public function extOrderId(): ?string
    {
        return $this->string(self::EXT_ORDER_ID);
    }

    public function totalAmount(): ?int
    {
        return $this->int(self::TOTAL_AMOUNT);
    }

    public function currencyCode(): ?string
    {
        return $this->string(self::CURRENCY_CODE);
    }

    public function description(): ?string
    {
        return $this->string(self::DESCRIPTION);
    }

    public function customerIp(): ?string
    {
        return $this->string(self::CUSTOMER_IP);
    }

    public function setCustomerIp(string $customerIp): void
    {
        $this->storage[self::CUSTOMER_IP] = $customerIp;
    }

    public function clientEmail(): ?string
    {
        return $this->string(self::CLIENT_EMAIL);
    }

    public function clientId(): ?string
    {
        return $this->string(self::CLIENT_ID);
    }

    public function firstName(): ?string
    {
        return $this->string(self::FIRST_NAME);
    }

    public function lastName(): ?string
    {
        return $this->string(self::LAST_NAME);
    }

    public function language(): ?string
    {
        return $this->string(self::LANGUAGE);
    }

    public function validityTime(): ?int
    {
        return $this->int(self::VALIDITY_TIME);
    }

    public function recurring(): ?Recurring
    {
        $recurring = $this->string(self::RECURRING);

        return $recurring === null ? null : Recurring::tryFrom($recurring);
    }

    public function payMethod(): ?PayMethod
    {
        $payMethods = $this->storage[self::PAY_METHODS] ?? null;
        $payMethod = is_array($payMethods) ? $payMethods['payMethod'] ?? null : null;
        $type = is_array($payMethod) ? $payMethod['type'] ?? null : null;
        $value = is_array($payMethod) ? $payMethod['value'] ?? null : null;

        if (! is_string($type) || ! is_string($value)) {
            return null;
        }

        return PayMethod::of($type, $value);
    }

    public function setPayMethod(PayMethod $payMethod): void
    {
        $this->storage[self::PAY_METHODS] = $payMethod->toArray();
    }

    public function payMethodType(): ?string
    {
        return $this->string(self::PAY_METHOD_TYPE);
    }

    public function setPayMethodType(string $payMethodType): void
    {
        $this->storage[self::PAY_METHOD_TYPE] = $payMethodType;
    }

    /**
     * @return non-empty-list<array<string, mixed>>|null
     */
    public function products(): ?array
    {
        $products = $this->storage[self::PRODUCTS] ?? null;

        if (! is_array($products) || $products === [] || ! array_is_list($products)) {
            return null;
        }

        /** @var non-empty-list<array<string, mixed>> $products */
        return $products;
    }

    public function setRedirectUri(string $redirectUri): void
    {
        $this->storage[self::REDIRECT_URI] = $redirectUri;
    }

    public function cardMaskedNumber(): ?string
    {
        return $this->string(self::CARD_MASKED_NUMBER);
    }

    public function setCardMaskedNumber(string $cardMaskedNumber): void
    {
        $this->storage[self::CARD_MASKED_NUMBER] = $cardMaskedNumber;
    }

    public function refund(): ?RefundDetails
    {
        $refund = $this->storage[self::REFUND] ?? null;

        if (! is_array($refund)) {
            return null;
        }

        /** @var array<string, mixed> $refund */
        return RefundDetails::fromArray($refund);
    }

    public function setRefund(RefundDetails $refund): void
    {
        $this->storage[self::REFUND] = $refund->toArray();
    }

    public function refundAmount(): ?int
    {
        return $this->int(self::REFUND_AMOUNT);
    }

    public function refundDescription(): ?string
    {
        return $this->string(self::REFUND_DESCRIPTION);
    }

    private function string(string $key): ?string
    {
        $value = $this->storage[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    private function int(string $key): ?int
    {
        $value = $this->storage[$key] ?? null;

        return is_int($value) ? $value : null;
    }
}
