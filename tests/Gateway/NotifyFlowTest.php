<?php

declare(strict_types=1);

namespace PayumPayU\Tests\Gateway;

use Payum\Core\Reply\HttpResponse;
use Payum\Core\Request\Notify;
use PayumPayU\Action\NotifyAction;
use PayumPayU\Model\Details;

final class NotifyFlowTest extends GatewayTestCase
{
    private const ORDER_COMPLETED = '{"order":{"orderId":"O1","extOrderId":"INV-1","status":"COMPLETED","payMethod":{"type":"PBL"}},'
        . '"localReceiptDateTime":"2026-10-23T10:00:05.000+02:00"}';
    private const ORDER_PENDING = '{"order":{"orderId":"O1","status":"PENDING"}}';
    private const REFUND_FINALIZED = '{"orderId":"O1","refund":{"refundId":"R1","amount":"2100","currencyCode":"PLN","status":"FINALIZED"}}';

    public function testASignedOrderNotificationUpdatesThePayment(): void
    {
        $details = self::convertedDetails([Details::ORDER_ID => 'O1', Details::STATUS => 'PENDING']);

        $reply = $this->notify(self::ORDER_COMPLETED, [NotifyAction::SIGNATURE_HEADER => self::sign(self::ORDER_COMPLETED)], $details);

        self::assertSame(200, $reply->getStatusCode());
        self::assertSame('OK', $reply->getContent());
        self::assertSame('COMPLETED', $details[Details::STATUS]);
        self::assertSame('PBL', $details[Details::PAY_METHOD_TYPE]);
    }

    public function testTheLegacyHeaderNameAndMd5AreAccepted(): void
    {
        $details = self::convertedDetails([Details::ORDER_ID => 'O1']);

        $reply = $this->notify(self::ORDER_PENDING, [NotifyAction::LEGACY_SIGNATURE_HEADER => self::sign(self::ORDER_PENDING, 'MD5')], $details);

        self::assertSame(200, $reply->getStatusCode());
        self::assertSame('PENDING', $details[Details::STATUS]);
    }

    public function testThePrimaryHeaderWinsOverTheLegacyOne(): void
    {
        $details = self::convertedDetails([Details::ORDER_ID => 'O1']);
        $headers = [
            NotifyAction::SIGNATURE_HEADER => self::sign(self::ORDER_PENDING),
            NotifyAction::LEGACY_SIGNATURE_HEADER => 'signature=00;algorithm=MD5',
        ];

        $reply = $this->notify(self::ORDER_PENDING, $headers, $details);

        self::assertSame(200, $reply->getStatusCode());
        self::assertSame('PENDING', $details[Details::STATUS]);
    }

    public function testStaleNotificationsAreAcknowledgedButIgnored(): void
    {
        $details = self::convertedDetails([Details::ORDER_ID => 'O1', Details::STATUS => 'COMPLETED']);

        $reply = $this->notify(self::ORDER_PENDING, [NotifyAction::SIGNATURE_HEADER => self::sign(self::ORDER_PENDING)], $details);

        self::assertSame(200, $reply->getStatusCode());
        self::assertSame('COMPLETED', $details[Details::STATUS]);
        self::assertArrayNotHasKey(Details::PAY_METHOD_TYPE, $details->getArrayCopy());
    }

    public function testARefundNotificationStoresTheRefund(): void
    {
        $details = self::convertedDetails([Details::ORDER_ID => 'O1', Details::STATUS => 'COMPLETED']);

        $reply = $this->notify(self::REFUND_FINALIZED, [NotifyAction::SIGNATURE_HEADER => self::sign(self::REFUND_FINALIZED)], $details);

        self::assertSame(200, $reply->getStatusCode());
        self::assertSame('COMPLETED', $details[Details::STATUS]);
        self::assertSame(
            ['refundId' => 'R1', 'status' => 'FINALIZED', 'amount' => 2100, 'currencyCode' => 'PLN', 'description' => null],
            $details[Details::REFUND],
        );
    }

    public function testAWrongSignatureIsRejectedWith401(): void
    {
        $details = self::convertedDetails([Details::ORDER_ID => 'O1', Details::STATUS => 'PENDING']);

        $headers = [NotifyAction::SIGNATURE_HEADER => self::sign('{"order":{"orderId":"O1","status":"COMPLETED"}}')];

        $reply = $this->notify(self::ORDER_COMPLETED, $headers, $details);

        self::assertSame(401, $reply->getStatusCode());
        self::assertSame('The notification signature does not match the second key of this point of sale.', $reply->getContent());
        self::assertSame('PENDING', $details[Details::STATUS]);
    }

    public function testAMissingSignatureIsRejectedWith401(): void
    {
        $reply = $this->notify(self::ORDER_COMPLETED, [], self::convertedDetails([Details::ORDER_ID => 'O1']));

        self::assertSame(401, $reply->getStatusCode());
        self::assertSame('The notification carries no OpenPayu-Signature header.', $reply->getContent());
    }

    public function testAnUnreadableBodyIsRejectedWith400(): void
    {
        $details = self::convertedDetails([Details::ORDER_ID => 'O1']);

        $reply = $this->notify('{"ping":true}', [NotifyAction::SIGNATURE_HEADER => self::sign('{"ping":true}')], $details);

        self::assertSame(400, $reply->getStatusCode());
        self::assertSame('The PayU notification is neither an order nor a refund notification.', $reply->getContent());
    }

    public function testANotificationForAnotherOrderIsRejectedWith400(): void
    {
        $details = self::convertedDetails([Details::ORDER_ID => 'O9', Details::STATUS => 'PENDING']);

        $reply = $this->notify(self::ORDER_COMPLETED, [NotifyAction::SIGNATURE_HEADER => self::sign(self::ORDER_COMPLETED)], $details);

        self::assertSame(400, $reply->getStatusCode());
        self::assertSame('The notification does not belong to this payment.', $reply->getContent());
        self::assertSame('PENDING', $details[Details::STATUS]);
    }

    public function testANotificationBeforeTheOrderIsStoredAsksPayUToRetry(): void
    {
        $reply = $this->notify(self::ORDER_COMPLETED, [NotifyAction::SIGNATURE_HEADER => self::sign(self::ORDER_COMPLETED)], self::convertedDetails());

        self::assertSame(412, $reply->getStatusCode());
        self::assertSame('The order is not stored yet, retry later.', $reply->getContent());
    }

    /**
     * @param array<string, string> $headers
     * @param \ArrayObject<string, mixed> $details
     */
    private function notify(string $body, array $headers, \ArrayObject $details): HttpResponse
    {
        $this->httpRequest->content = $body;
        $this->headers = $headers;

        $reply = $this->gateway()->execute(new Notify($details), true);

        self::assertInstanceOf(HttpResponse::class, $reply);

        return $reply;
    }
}
