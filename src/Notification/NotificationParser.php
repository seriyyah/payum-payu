<?php

declare(strict_types=1);

namespace PayumPayU\Notification;

use PayumPayU\Json\Document;
use PayumPayU\Json\Exception\DocumentException;
use PayumPayU\Model\OrderStatus;
use PayumPayU\Model\RefundDetails;
use PayumPayU\Model\RefundStatus;
use PayumPayU\Notification\Exception\MalformedNotificationException;

final class NotificationParser
{
    /**
     * @throws MalformedNotificationException
     */
    public function parse(string $json): OrderNotification|RefundNotification
    {
        try {
            $document = Document::fromJson($json);

            if ($document->has('order')) {
                return $this->order($document->document('order'));
            }

            if ($document->has('refund')) {
                return $this->refund($document);
            }
        } catch (DocumentException $exception) {
            throw MalformedNotificationException::fromDocumentException($exception);
        }

        throw MalformedNotificationException::unknownShape();
    }

    private function order(Document $order): OrderNotification
    {
        $status = $order->string('status');

        return new OrderNotification(
            $order->string('orderId'),
            $order->optionalString('extOrderId'),
            OrderStatus::tryFrom($status) ?? throw MalformedNotificationException::unknownStatus('order', $status),
            $order->optionalDocument('payMethod')?->optionalString('type'),
        );
    }

    private function refund(Document $document): RefundNotification
    {
        $refund = $document->document('refund');
        $status = $refund->string('status');

        return new RefundNotification(
            $document->string('orderId'),
            $document->optionalString('extOrderId'),
            new RefundDetails(
                $refund->string('refundId'),
                RefundStatus::tryFrom($status) ?? throw MalformedNotificationException::unknownStatus('refund', $status),
                $refund->optionalInt('amount'),
                $refund->optionalString('currencyCode'),
                $refund->optionalString('description'),
            ),
        );
    }
}
