<?php

declare(strict_types=1);

namespace PayumPayU\Tests\Notification;

use PayumPayU\Model\OrderStatus;
use PayumPayU\Model\RefundStatus;
use PayumPayU\Notification\Exception\MalformedNotificationException;
use PayumPayU\Notification\NotificationParser;
use PayumPayU\Notification\OrderNotification;
use PayumPayU\Notification\RefundNotification;
use PayumPayU\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class NotificationParserTest extends TestCase
{
    public function testParsesAnOrderNotification(): void
    {
        $notification = (new NotificationParser())->parse(<<<'JSON'
            {"order":{"orderId":"LDLW5N7MF4140324GUEST000P01","extOrderId":"INV-1","orderCreateDate":"2026-10-23T10:00:00.000+02:00",
            "notifyUrl":"https://shop.example/notify","customerIp":"127.0.0.1","merchantPosId":"145227","description":"Insurance",
            "currencyCode":"PLN","totalAmount":"2100","buyer":{"email":"buyer@example.com"},"payMethod":{"type":"PBL"},
            "products":[{"name":"Insurance","unitPrice":"2100","quantity":"1"}],"status":"COMPLETED"},
            "localReceiptDateTime":"2026-10-23T10:00:05.000+02:00","properties":[{"name":"PAYMENT_ID","value":"151471228"}]}
            JSON);

        self::assertInstanceOf(OrderNotification::class, $notification);
        self::assertSame('LDLW5N7MF4140324GUEST000P01', $notification->orderId);
        self::assertSame('INV-1', $notification->extOrderId);
        self::assertSame(OrderStatus::Completed, $notification->status);
        self::assertSame('PBL', $notification->payMethodType);
    }

    public function testParsesAnOrderNotificationWithoutPayMethod(): void
    {
        $notification = (new NotificationParser())->parse('{"order":{"orderId":"O1","status":"PENDING"}}');

        self::assertInstanceOf(OrderNotification::class, $notification);
        self::assertNull($notification->extOrderId);
        self::assertNull($notification->payMethodType);
    }

    public function testParsesARefundNotification(): void
    {
        $notification = (new NotificationParser())->parse(<<<'JSON'
            {"orderId":"O1","extOrderId":"INV-1","refund":{"refundId":"R1","extRefundId":"ext","amount":"500","currencyCode":"PLN",
            "status":"FINALIZED","statusDateTime":"2026-10-23T11:00:00.000+02:00","reason":"refund","reasonDescription":"Damaged goods",
            "refundDate":"2026-10-23T11:00:00.000+02:00"}}
            JSON);

        self::assertInstanceOf(RefundNotification::class, $notification);
        self::assertSame('O1', $notification->orderId);
        self::assertSame('INV-1', $notification->extOrderId);
        self::assertSame('R1', $notification->refund->refundId);
        self::assertSame(RefundStatus::Finalized, $notification->refund->status);
        self::assertSame(500, $notification->refund->amount);
        self::assertSame('PLN', $notification->refund->currencyCode);
        self::assertNull($notification->refund->description);
    }

    #[DataProvider('malformedBodies')]
    public function testRejectsMalformedBodies(string $json, string $message): void
    {
        self::assertThrows(
            MalformedNotificationException::class,
            static fn (): OrderNotification|RefundNotification => (new NotificationParser())->parse($json),
            $message,
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function malformedBodies(): iterable
    {
        yield 'not json' => ['{', 'The PayU notification cannot be read: The JSON document is malformed'];
        yield 'neither order nor refund' => ['{"ping":true}', 'The PayU notification is neither an order nor a refund notification.'];
        yield 'order without id' => [
            '{"order":{"status":"NEW"}}',
            'The PayU notification cannot be read: The field "$.order.orderId" is missing.',
        ];

        yield 'unknown order status' => [
            '{"order":{"orderId":"O1","status":"REJECTED"}}',
            'The PayU notification carries an unknown order status "REJECTED".',
        ];

        yield 'unknown refund status' => [
            '{"orderId":"O1","refund":{"refundId":"R1","status":"DONE"}}',
            'The PayU notification carries an unknown refund status "DONE".',
        ];

        yield 'refund without order id' => [
            '{"refund":{"refundId":"R1","status":"PENDING"}}',
            'The PayU notification cannot be read: The field "$.orderId" is missing.',
        ];
    }
}
