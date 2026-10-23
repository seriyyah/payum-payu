# Recurring payments

PayU stores cards as **card tokens** per buyer (`buyer.extCustomerId`). The flow has two parts.

## First payment (`FIRST`)

The buyer pays once on the PayU checkout page and agrees to card storage. Set the recurring mode and let PayU tokenize
the card:

```php
$payment->setClientId('customer-42');        // becomes buyer.extCustomerId; required for tokens
$payment->setClientEmail('buyer@example.com');
$payment->setDetails([
    Details::RECURRING => Recurring::First->value,
]);
```

Depending on your PayU contract the first order may need a card token obtained with the PayU secure form widget. In that
case put it into the details before capturing:

```php
$details[Details::PAY_METHODS] = PayMethod::cardToken($tokenFromWidget)->toArray();
```

PayU returns the masked card number with the order; it is stored as `cardMaskedNumber`.

## Later payments (`STANDARD`)

Merchant-initiated charges run without the buyer, typically from a cron job or a message consumer:

```php
$payment = clone $firstPayment;              // same clientId and clientEmail
$payment->setNumber('INV-2026-0002');
$payment->setDetails([
    Details::RECURRING => Recurring::Standard->value,
    Details::CUSTOMER_IP => '203.0.113.10',  // no HTTP request here, set it yourself
    Details::CARD_MASKED_NUMBER => '4444********1111', // optional: charge this card specifically
]);

$token = $payum->getTokenFactory()->createCaptureToken('payu', $payment, 'done');
$payum->getGateway('payu')->execute(new Capture($token));
```

When no pay method is set, the gateway:

1. requests a `trusted_merchant` OAuth token for the buyer (`clientEmail` + `clientId`),
2. calls `GET /api/v2_1/paymethods`,
3. picks the buyer's **preferred active** card, else the first active one, restricted to `cardMaskedNumber` when given,
4. sends `recurring: STANDARD` with `payMethods.payMethod = {type: CARD_TOKEN, value: ...}`.

Card-token orders are usually settled synchronously: PayU returns `SUCCESS` without a `redirectUri`, the gateway
synchronises the order at once and `GetHumanStatus` reports `captured`. 3-D Secure may still require a redirect
(`WARNING_CONTINUE_3DS`); the gateway handles that like a normal checkout.

Failures:

- `CardTokenNotFoundException`: the buyer has no active stored card (expired, deleted, never saved).
- `MissingDetailException`: `clientEmail`, `clientId` or `customerIp` is missing.

## Listing stored cards

```php
use PayumPayU\Request\RetrievePayMethods;

$request = new RetrievePayMethods('buyer@example.com', 'customer-42');
$payum->getGateway('payu')->execute($request);

foreach ($request->result()->activeCardTokens() as $card) {
    echo $card->cardBrand, ' ', $card->cardNumberMasked, $card->preferred ? ' (preferred)' : '', PHP_EOL;
}
```
