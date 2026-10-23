# Notifications

PayU confirms every status change with a server-to-server `POST` to the `notifyUrl` sent with the order. The package
sets that URL to a Payum **notify token**, so Payum's standard notify controller (`/payment/notify/{payum_token}` in
PayumBundle, `notify.php` in plain PHP) routes the call to this gateway's `Notify` action.

## What the action does

1. Reads the raw body through Payum's `GetHttpRequest` and the `OpenPayu-Signature` header through the configured
   `HeaderProviderInterface` (`X-OpenPayU-Signature` is accepted as well).
2. Verifies the signature: `hash(algorithm, body . signature_key)` compared in constant time. MD5, SHA-1 and SHA-256 are
   supported, spelled with or without the dash.
3. Parses the body as an order notification (`{"order": {...}}`) or a refund notification (`{"refund": {...}}`).
4. Checks that the notification is about the stored `orderId`.
5. Applies it:
   - order: the status is updated only if it moves forward (`NEW → PENDING → WAITING_FOR_CONFIRMATION → COMPLETED |
     CANCELED`). PayU does not guarantee ordering, so a `PENDING` arriving after `COMPLETED` is ignored.
     `payMethodType` is stored when present.
   - refund: the refund object is stored under `details.refund`.
6. Replies.

## Replies

| HTTP | When | PayU behaviour |
| --- | --- | --- |
| 200 `OK` | applied, or stale and ignored | done |
| 401 | missing or wrong signature | retries, then gives up |
| 400 | unreadable body, unknown status, or a different `orderId` | retries, then gives up |
| 412 | our `orderId` is not stored yet (notification raced the order creation) | retries with back-off, which is what we want |

PayU retries non-200 responses with increasing delays for roughly 72 hours.

## Reacting to notifications

Payum dispatches `payum.gateway.post_execute` after each request. Listen for `Payum\Core\Request\Notify` there to
update your own domain (mark an invoice paid, send an e-mail). Read the outcome through `GetHumanStatus` rather than the
raw details:

```php
use Payum\Core\Bridge\Symfony\Event\ExecuteEvent;
use Payum\Core\Request\GetHumanStatus;
use Payum\Core\Request\Notify;

public function onPostExecute(ExecuteEvent $event): void
{
    $request = $event->getContext()->getRequest();

    if (!$request instanceof Notify) {
        return;
    }

    $status = new GetHumanStatus($request->getFirstModel());
    $event->getContext()->getGateway()->execute($status);

    if ($status->isCaptured()) {
        // settle your order
    }
}
```

## Testing locally

PayU must reach your notify URL, so expose your dev server with a tunnel (ngrok, cloudflared) or replay a notification
manually:

```bash
BODY='{"order":{"orderId":"O1","status":"COMPLETED"}}'
SIG=$(printf '%s%s' "$BODY" "$SECOND_KEY" | openssl dgst -sha256 | awk '{print $2}')
curl -X POST "$NOTIFY_URL" \
  -H "Content-Type: application/json" \
  -H "OpenPayu-Signature: sender=checkout;signature=$SIG;algorithm=SHA-256;content=DOCUMENT" \
  --data "$BODY"
```
