<?php

declare(strict_types=1);

namespace PayumPayU\Tests\Model;

use PayumPayU\Model\Details;
use PayumPayU\Model\Exception\MissingDetailException;
use PayumPayU\Model\OrderStatus;
use PayumPayU\Model\PayMethod;
use PayumPayU\Model\Recurring;
use PayumPayU\Model\RefundDetails;
use PayumPayU\Model\RefundStatus;
use PayumPayU\Tests\TestCase;

final class DetailsTest extends TestCase
{
    public function testReadsConvertedPaymentValues(): void
    {
        $details = self::details([
            Details::ORDER_ID => 'ORD-1',
            Details::STATUS => 'PENDING',
            Details::EXT_ORDER_ID => 'INV-1',
            Details::TOTAL_AMOUNT => 2100,
            Details::CURRENCY_CODE => 'PLN',
            Details::DESCRIPTION => 'Insurance',
            Details::CUSTOMER_IP => '10.0.0.1',
            Details::CLIENT_EMAIL => 'buyer@example.com',
            Details::CLIENT_ID => 'C-1',
            Details::FIRST_NAME => 'Jan',
            Details::LAST_NAME => 'Kowalski',
            Details::LANGUAGE => 'pl',
            Details::VALIDITY_TIME => 600,
            Details::RECURRING => 'STANDARD',
            Details::PAY_METHODS => ['payMethod' => ['type' => 'PBL', 'value' => 'c']],
            Details::PAY_METHOD_TYPE => 'PBL',
            Details::PRODUCTS => [['name' => 'A', 'unitPrice' => 2100, 'quantity' => 1]],
            Details::CARD_MASKED_NUMBER => '4444********1111',
            Details::REFUND => ['refundId' => 'R1', 'status' => 'PENDING'],
            Details::REFUND_AMOUNT => 100,
            Details::REFUND_DESCRIPTION => 'Partial',
        ]);

        self::assertSame('ORD-1', $details->orderId());
        self::assertSame('ORD-1', $details->requireOrderId());
        self::assertSame(OrderStatus::Pending, $details->status());
        self::assertSame('PENDING', $details->rawStatus());
        self::assertSame('INV-1', $details->extOrderId());
        self::assertSame(2100, $details->totalAmount());
        self::assertSame('PLN', $details->currencyCode());
        self::assertSame('Insurance', $details->description());
        self::assertSame('10.0.0.1', $details->customerIp());
        self::assertSame('buyer@example.com', $details->clientEmail());
        self::assertSame('C-1', $details->clientId());
        self::assertSame('Jan', $details->firstName());
        self::assertSame('Kowalski', $details->lastName());
        self::assertSame('pl', $details->language());
        self::assertSame(600, $details->validityTime());
        self::assertSame(Recurring::Standard, $details->recurring());
        self::assertEquals(PayMethod::payByLink('c'), $details->payMethod());
        self::assertSame('PBL', $details->payMethodType());
        self::assertSame([['name' => 'A', 'unitPrice' => 2100, 'quantity' => 1]], $details->products());
        self::assertSame('4444********1111', $details->cardMaskedNumber());
        self::assertEquals(new RefundDetails('R1', RefundStatus::Pending, null, null, null), $details->refund());
        self::assertSame(100, $details->refundAmount());
        self::assertSame('Partial', $details->refundDescription());
    }

    public function testEverythingIsNullOnAnEmptyModel(): void
    {
        $details = self::details([]);

        self::assertNull($details->orderId());
        self::assertNull($details->status());
        self::assertNull($details->rawStatus());
        self::assertNull($details->extOrderId());
        self::assertNull($details->totalAmount());
        self::assertNull($details->currencyCode());
        self::assertNull($details->description());
        self::assertNull($details->customerIp());
        self::assertNull($details->clientEmail());
        self::assertNull($details->clientId());
        self::assertNull($details->firstName());
        self::assertNull($details->lastName());
        self::assertNull($details->language());
        self::assertNull($details->validityTime());
        self::assertNull($details->recurring());
        self::assertNull($details->payMethod());
        self::assertNull($details->payMethodType());
        self::assertNull($details->products());
        self::assertNull($details->cardMaskedNumber());
        self::assertNull($details->refund());
        self::assertNull($details->refundAmount());
        self::assertNull($details->refundDescription());
    }

    public function testValuesOfTheWrongTypeAreIgnored(): void
    {
        $details = self::details([
            Details::ORDER_ID => 123,
            Details::STATUS => 'UNKNOWN_STATE',
            Details::TOTAL_AMOUNT => '2100',
            Details::RECURRING => 'WEEKLY',
            Details::PAY_METHODS => 'card',
            Details::PRODUCTS => ['name' => 'not a list'],
            Details::REFUND => 'none',
        ]);

        self::assertNull($details->orderId());
        self::assertNull($details->status());
        self::assertSame('UNKNOWN_STATE', $details->rawStatus());
        self::assertNull($details->totalAmount());
        self::assertNull($details->recurring());
        self::assertNull($details->payMethod());
        self::assertNull($details->products());
        self::assertNull($details->refund());
        self::assertNull(self::details([Details::PRODUCTS => []])->products());
        self::assertNull(self::details([Details::PAY_METHODS => ['payMethod' => ['type' => 'PBL']]])->payMethod());
        self::assertNull(self::details([Details::PAY_METHODS => ['payMethod' => 'x']])->payMethod());
        self::assertNull(self::details([Details::PAY_METHODS => ['payMethod' => ['value' => 'x']]])->payMethod());
        self::assertNull(self::details([Details::PAY_METHODS => ['payMethod' => ['type' => 1, 'value' => 'x']]])->payMethod());
    }

    public function testWritesThroughToTheWrappedModel(): void
    {
        $model = new \ArrayObject();
        $details = new Details($model);

        $details->setOrderId('ORD-2');
        $details->setStatus(OrderStatus::Completed);
        $details->setCustomerIp('192.168.0.1');
        $details->setPayMethod(PayMethod::cardToken('TOK'));
        $details->setPayMethodType('CARD_TOKEN');
        $details->setRedirectUri('https://pay.example/redirect');
        $details->setCardMaskedNumber('5555********4444');
        $details->setRefund(new RefundDetails('R2', RefundStatus::Finalized, 2100, 'PLN', 'Refund'));

        self::assertSame([
            Details::ORDER_ID => 'ORD-2',
            Details::STATUS => 'COMPLETED',
            Details::CUSTOMER_IP => '192.168.0.1',
            Details::PAY_METHODS => ['payMethod' => ['type' => 'CARD_TOKEN', 'value' => 'TOK']],
            Details::PAY_METHOD_TYPE => 'CARD_TOKEN',
            Details::REDIRECT_URI => 'https://pay.example/redirect',
            Details::CARD_MASKED_NUMBER => '5555********4444',
            Details::REFUND => ['refundId' => 'R2', 'status' => 'FINALIZED', 'amount' => 2100, 'currencyCode' => 'PLN', 'description' => 'Refund'],
        ], $model->getArrayCopy());
    }

    public function testRequireOrderIdExplainsWhatIsMissing(): void
    {
        self::assertThrows(
            MissingDetailException::class,
            static fn (): string => self::details([])->requireOrderId(),
            'The payment detail "orderId" is required: the order has not been created at PayU yet',
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function details(array $data): Details
    {
        return new Details(new \ArrayObject($data));
    }
}
