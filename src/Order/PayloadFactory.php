<?php

declare(strict_types=1);

namespace PayumPayU\Order;

use PayumPayU\Model\Details;
use PayumPayU\Model\Exception\MissingDetailException;

/**
 * Builds the body of POST /api/v2_1/orders from the payment details.
 */
final class PayloadFactory
{
    /**
     * @return array<string, mixed>
     */
    public function create(Details $details, string $merchantPosId, string $continueUrl, string $notifyUrl): array
    {
        $description = $details->description() ?? throw MissingDetailException::forKey(Details::DESCRIPTION, 'convert the payment first');
        $totalAmount = $details->totalAmount() ?? throw MissingDetailException::forKey(Details::TOTAL_AMOUNT, 'convert the payment first');

        $payload = [
            'continueUrl' => $continueUrl,
            'notifyUrl' => $notifyUrl,
            'customerIp' => $details->customerIp() ?? throw MissingDetailException::forKey(
                Details::CUSTOMER_IP,
                'set the buyer IP in the details or capture inside an HTTP request',
            ),
            'merchantPosId' => $merchantPosId,
            'description' => $description,
            'currencyCode' => $details->currencyCode()
                ?? throw MissingDetailException::forKey(Details::CURRENCY_CODE, 'convert the payment first'),
            'totalAmount' => $totalAmount,
            'extOrderId' => $details->extOrderId()
                ?? throw MissingDetailException::forKey(Details::EXT_ORDER_ID, 'convert the payment first'),
            'products' => $details->products() ?? [[
                'name' => $description,
                'unitPrice' => $totalAmount,
                'quantity' => 1,
            ]],
        ];

        $buyer = $this->buyer($details);

        if ($buyer !== []) {
            $payload['buyer'] = $buyer;
        }

        return $payload + $this->optional($details);
    }

    /**
     * @return array<string, string>
     */
    private function buyer(Details $details): array
    {
        return array_filter([
            'email' => $details->clientEmail(),
            'firstName' => $details->firstName(),
            'lastName' => $details->lastName(),
            'extCustomerId' => $details->clientId(),
            'language' => $details->language(),
        ], static fn (?string $value): bool => $value !== null);
    }

    /**
     * @return array<string, mixed>
     */
    private function optional(Details $details): array
    {
        return array_filter([
            'validityTime' => $details->validityTime(),
            'recurring' => $details->recurring()?->value,
            'payMethods' => $details->payMethod()?->toArray(),
        ], static fn (mixed $value): bool => $value !== null);
    }
}
