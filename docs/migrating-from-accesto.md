# Migrating from accesto/payum-pay-u

`accesto/payum-pay-u` wraps the static `openpayu/openpayu` SDK. This package talks to the REST API directly, verifies
notification signatures and types every response. The gateway name stays `payu`, so stored gateway configurations keep
working after the option rename below.

## Composer

```bash
composer remove accesto/payum-pay-u openpayu/openpayu
composer require payum-payu/payum-payu
```

## Gateway options

| accesto | payum-payu |
| --- | --- |
| `environment` | `environment` (unchanged: `secure` / `sandbox`) |
| `pos_id` | `pos_id` |
| `signature_key` | `signature_key` |
| `oauth_client_id` | `oauth_client_id` |
| `oauth_secret` | **`oauth_client_secret`** |

## Classes

| accesto | payum-payu |
| --- | --- |
| `Accesto\Component\Payum\PayU\PayUGatewayFactory` | `PayumPayU\PayUGatewayFactory` |
| `OpenPayUWrapper::RECURRING_FIRST` / `RECURRING_STANDARD` | `PayumPayU\Model\Recurring::First` / `::Standard` |
| `SetPayU` request | `PayumPayU\Request\CreateOrder` (executed by `Capture`, you rarely need it) |
| `RefundPayU` request | `PayumPayU\Request\RefundOrder` (executed by `Refund`) |
| `Exception\PayUException` | `PayumPayU\Rest\Exception\ApiException` (`->status`, `->httpStatusCode`) |
| `\OpenPayU_Exception` | `PayumPayU\Rest\Exception\TransportException` / `InvalidResponseException` |

## Details keys

| accesto | payum-payu |
| --- | --- |
| `client_id` | `clientId` |
| `client_email` | `clientEmail` |
| `creditCardMaskedNumber` | `cardMaskedNumber` |
| `payUResponse` (raw SDK object) | not stored; typed values are stored instead |
| `status = REFUND_FINALIZED` etc. | `refund.status`; order `status` keeps the order state |

Payments that are still in flight at migration time keep `orderId` and `status`, which is all `Sync`, `Notify` and
`GetHumanStatus` need. Rows whose `status` is a legacy `REFUND_*` value report `unknown` until a `Sync` runs.

## Behaviour changes

- **Customer IP.** accesto read `$_SERVER['REMOTE_ADDR']` inside the convert action, which forced server-side
  charges to fake the global. Set `details.customerIp` instead; inside an HTTP request it is taken from Payum's
  `GetHttpRequest` automatically.
- **Recurring flag.** The order carries `recurring` exactly as set in the details. accesto never sent `FIRST`; if
  your first payments relied on that, leave `recurring` unset for them.
- **Notifications are verified.** A notification without a valid `OpenPayu-Signature` is rejected with HTTP 401.
  Make sure your reverse proxy forwards that header.
- **Out-of-order notifications** no longer downgrade a `COMPLETED` payment to `PENDING`.
- **Statuses.** `WAITING_FOR_CONFIRMATION` maps to `pending` (accesto: unknown). `REJECTED` is not a PayU REST status
  and is no longer special-cased.
- **No `GetHumanStatus` inside `Notify`.** Listen to `payum.gateway.post_execute` for `Notify` requests and execute
  `GetHumanStatus` yourself ([notifications.md](notifications.md)).
