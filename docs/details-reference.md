# Payment details reference

The gateway stores its state in the Payum payment details array. `PayumPayU\Model\Details` is a typed accessor over
that array; the constants below are its keys.

| Key | Constant | Set by | Meaning |
| --- | --- | --- | --- |
| `orderId` | `ORDER_ID` | gateway | PayU order ID |
| `status` | `STATUS` | gateway | PayU order status (`NEW`, `PENDING`, `WAITING_FOR_CONFIRMATION`, `COMPLETED`, `CANCELED`) |
| `extOrderId` | `EXT_ORDER_ID` | convert | your order number (`PaymentInterface::getNumber()`) |
| `totalAmount` | `TOTAL_AMOUNT` | convert | amount in minor units |
| `currencyCode` | `CURRENCY_CODE` | convert | ISO 4217 code |
| `description` | `DESCRIPTION` | convert | order description, also the default product name |
| `clientEmail` | `CLIENT_EMAIL` | convert | `buyer.email` |
| `clientId` | `CLIENT_ID` | convert | `buyer.extCustomerId`, needed for card tokens |
| `customerIp` | `CUSTOMER_IP` | you / gateway | buyer IP; taken from the HTTP request when absent |
| `firstName` | `FIRST_NAME` | you | `buyer.firstName` |
| `lastName` | `LAST_NAME` | you | `buyer.lastName` |
| `language` | `LANGUAGE` | you | `buyer.language` (checkout page language) |
| `validityTime` | `VALIDITY_TIME` | you | seconds the order stays payable |
| `recurring` | `RECURRING` | you | `FIRST` or `STANDARD` |
| `payMethods` | `PAY_METHODS` | you / gateway | `{payMethod: {type, value}}`; `PayMethod::cardToken()`, `PayMethod::payByLink()` |
| `payMethodType` | `PAY_METHOD_TYPE` | gateway | how the buyer paid (`PBL`, `CARD_TOKEN`, `INSTALLMENTS`, ...) |
| `products` | `PRODUCTS` | you | list of `{name, unitPrice, quantity}`; defaults to one product |
| `redirectUri` | `REDIRECT_URI` | gateway | where the buyer was sent |
| `cardMaskedNumber` | `CARD_MASKED_NUMBER` | gateway / you | masked card; input for `STANDARD` charges |
| `refund` | `REFUND` | gateway | latest refund `{refundId, status, amount, currencyCode, description}` |
| `refundAmount` | `REFUND_AMOUNT` | you | partial refund amount |
| `refundDescription` | `REFUND_DESCRIPTION` | you | refund description |

Values the gateway reads must have the documented type; anything else is treated as absent. For example a numeric
string in `totalAmount` is ignored and `PayloadFactory` reports it as missing.
