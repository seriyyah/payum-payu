# Refunds and cancellation

## Refund

```php
use Payum\Core\Request\Refund;

$payum->getGateway('payu')->execute(new Refund($payment));
```

Refunds the whole order with the description `Refund`. For a partial refund or a custom description set the details
before executing:

```php
$details = $payment->getDetails();
$details[Details::REFUND_AMOUNT] = 500;              // minor units
$details[Details::REFUND_DESCRIPTION] = 'Damaged goods';
$payment->setDetails($details);
```

PayU answers with the refund in status `PENDING`; the result is stored under `details.refund`
(`refundId`, `status`, `amount`, `currencyCode`, `description`). The final `FINALIZED` or `CANCELED` state arrives
through a [refund notification](notifications.md). Once it is `FINALIZED`, `GetHumanStatus` reports `refunded`.

Several partial refunds are possible; the details keep the latest one. Keep your own ledger if you need the history.

## Cancel

```php
use Payum\Core\Request\Cancel;

$payum->getGateway('payu')->execute(new Cancel($payment));
```

Sends `DELETE /api/v2_1/orders/{orderId}` and sets `status` to `CANCELED`. Only orders that are not yet completed can
be cancelled; PayU rejects others with an `ApiException`.

## Sync

```php
use Payum\Core\Request\Sync;

$payum->getGateway('payu')->execute(new Sync($payment));
```

Fetches the order and updates `status` and `payMethodType`. A payment without an `orderId` is left untouched. Use it
as a reconciliation fallback when a notification was lost.
