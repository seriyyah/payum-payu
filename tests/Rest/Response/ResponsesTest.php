<?php

declare(strict_types=1);

namespace PayumPayU\Tests\Rest\Response;

use PayumPayU\Json\Document;
use PayumPayU\Json\Exception\DocumentException;
use PayumPayU\Model\OrderStatus;
use PayumPayU\Model\RefundStatus;
use PayumPayU\Rest\Response\CardToken;
use PayumPayU\Rest\Response\OrderCreated;
use PayumPayU\Rest\Response\OrderRetrieved;
use PayumPayU\Rest\Response\PayMethods;
use PayumPayU\Rest\Response\RefundCreated;
use PayumPayU\Rest\Response\Status;
use PayumPayU\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ResponsesTest extends TestCase
{
    #[DataProvider('statusCodes')]
    public function testStatusAcceptance(string $code, bool $accepted): void
    {
        $status = Status::fromDocument(Document::fromArray(['statusCode' => $code, 'statusDesc' => 'desc', 'codeLiteral' => 'LIT']));

        self::assertSame($code, $status->statusCode);
        self::assertSame('desc', $status->statusDesc);
        self::assertSame('LIT', $status->codeLiteral);
        self::assertSame($accepted, $status->isAccepted());
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function statusCodes(): iterable
    {
        yield 'success' => ['SUCCESS', true];
        yield '3ds' => ['WARNING_CONTINUE_3DS', true];
        yield 'cvv' => ['WARNING_CONTINUE_CVV', true];
        yield 'redirect' => ['WARNING_CONTINUE_REDIRECT', true];
        yield 'invalid value' => ['ERROR_VALUE_INVALID', false];
        yield 'unauthorized' => ['UNAUTHORIZED', false];
    }

    public function testOrderCreated(): void
    {
        $created = OrderCreated::fromDocument(Document::fromArray([
            'status' => ['statusCode' => 'SUCCESS'],
            'redirectUri' => 'https://merch-prod.snd.payu.com/pay/?orderId=1',
            'orderId' => 'WZHF5FFDRJ140731GUEST000P01',
            'extOrderId' => 'INV-1',
            'payMethods' => ['payMethod' => ['card' => ['number' => '4444********1111']]],
        ]));

        self::assertSame('WZHF5FFDRJ140731GUEST000P01', $created->orderId);
        self::assertSame('INV-1', $created->extOrderId);
        self::assertSame('https://merch-prod.snd.payu.com/pay/?orderId=1', $created->redirectUri);
        self::assertSame('4444********1111', $created->cardMaskedNumber);
        self::assertTrue($created->status->isAccepted());
    }

    public function testOrderCreatedWithoutOptionalFields(): void
    {
        $created = OrderCreated::fromDocument(Document::fromArray(['status' => ['statusCode' => 'SUCCESS'], 'orderId' => 'O1']));

        self::assertNull($created->extOrderId);
        self::assertNull($created->redirectUri);
        self::assertNull($created->cardMaskedNumber);
    }

    /**
     * @param array<string, mixed> $payMethods
     */
    #[DataProvider('incompleteCardPayMethods')]
    public function testOrderCreatedWithoutACardNumberInThePayMethod(array $payMethods): void
    {
        $created = OrderCreated::fromDocument(Document::fromArray(['status' => ['statusCode' => 'SUCCESS'], 'orderId' => 'O1', 'payMethods' => $payMethods]));

        self::assertNull($created->cardMaskedNumber);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function incompleteCardPayMethods(): iterable
    {
        yield 'no pay method' => [[]];
        yield 'no card' => [['payMethod' => ['type' => 'PBL']]];
        yield 'no number' => [['payMethod' => ['card' => []]]];
    }

    public function testOrderRetrieved(): void
    {
        $retrieved = OrderRetrieved::fromDocument(Document::fromArray([
            'orders' => [[
                'orderId' => 'O1',
                'status' => 'COMPLETED',
                'payMethod' => ['type' => 'CARD_TOKEN'],
                'totalAmount' => '2100',
                'currencyCode' => 'PLN',
            ]],
            'status' => ['statusCode' => 'SUCCESS'],
        ]));

        self::assertSame('O1', $retrieved->orderId);
        self::assertSame(OrderStatus::Completed, $retrieved->status);
        self::assertSame('CARD_TOKEN', $retrieved->payMethodType);
        self::assertSame(2100, $retrieved->totalAmount);
        self::assertSame('PLN', $retrieved->currencyCode);
    }

    public function testOrderRetrievedWithoutOptionalFields(): void
    {
        $retrieved = OrderRetrieved::fromDocument(Document::fromArray(['orders' => [['orderId' => 'O1', 'status' => 'NEW']]]));

        self::assertNull($retrieved->payMethodType);
        self::assertNull($retrieved->totalAmount);
        self::assertNull($retrieved->currencyCode);
    }

    public function testOrderRetrievedRequiresAnOrder(): void
    {
        self::assertThrows(
            DocumentException::class,
            static fn (): OrderRetrieved => OrderRetrieved::fromDocument(Document::fromArray(['orders' => []])),
            'The field "$.orders[0]" is missing.',
        );
    }

    public function testOrderRetrievedRejectsUnknownStatuses(): void
    {
        self::assertThrows(
            DocumentException::class,
            static fn (): OrderRetrieved => OrderRetrieved::fromDocument(Document::fromArray(['orders' => [['orderId' => 'O1', 'status' => 'REJECTED']]])),
            'The field "$.orders[0].status" must be a known order status, got "REJECTED".',
        );
    }

    public function testRefundCreated(): void
    {
        $created = RefundCreated::fromDocument(Document::fromArray([
            'orderId' => 'O1',
            'refund' => ['refundId' => 'R1', 'status' => 'PENDING', 'amount' => '500', 'currencyCode' => 'PLN', 'description' => 'Refund'],
            'status' => ['statusCode' => 'SUCCESS', 'statusDesc' => 'Refund queued'],
        ]));

        self::assertSame('O1', $created->orderId);
        self::assertSame('R1', $created->refund->refundId);
        self::assertSame(RefundStatus::Pending, $created->refund->status);
        self::assertSame(500, $created->refund->amount);
        self::assertSame('PLN', $created->refund->currencyCode);
        self::assertSame('Refund', $created->refund->description);
        self::assertSame('Refund queued', $created->status->statusDesc);
    }

    public function testRefundCreatedRejectsUnknownStatuses(): void
    {
        self::assertThrows(
            DocumentException::class,
            static fn (): RefundCreated => RefundCreated::fromDocument(Document::fromArray([
                'orderId' => 'O1',
                'refund' => ['refundId' => 'R1', 'status' => 'DONE'],
                'status' => ['statusCode' => 'SUCCESS'],
            ])),
            'The field "$.refund.status" must be a known refund status, got "DONE".',
        );
    }

    public function testPayMethodsListsCardTokens(): void
    {
        $payMethods = PayMethods::fromDocument(Document::fromArray([
            'cardTokens' => [
                [
                    'cardExpirationYear' => '2030',
                    'cardExpirationMonth' => '12',
                    'cardNumberMasked' => '4444********1111',
                    'cardBrand' => 'VISA',
                    'value' => 'TOK_ACTIVE',
                    'preferred' => true,
                    'status' => 'ACTIVE',
                ],
                ['cardNumberMasked' => '5555********4444', 'value' => 'TOK_EXPIRED', 'status' => 'EXPIRED'],
            ],
            'payByLinks' => [],
            'status' => ['statusCode' => 'SUCCESS'],
        ]));

        self::assertCount(2, $payMethods->cardTokens);
        $active = $payMethods->activeCardTokens();
        self::assertCount(1, $active);
        self::assertSame('TOK_ACTIVE', $active[0]->value);
        self::assertTrue($active[0]->preferred);
        self::assertTrue($active[0]->isActive());
        self::assertSame('VISA', $active[0]->cardBrand);
        self::assertSame('12', $active[0]->cardExpirationMonth);
        self::assertSame('2030', $active[0]->cardExpirationYear);
        self::assertFalse($payMethods->cardTokens[1]->preferred);
        self::assertFalse($payMethods->cardTokens[1]->isActive());
        self::assertNull($payMethods->cardTokens[1]->cardBrand);
    }

    public function testActiveCardTokensAreAList(): void
    {
        $payMethods = new PayMethods([
            new CardToken('OLD', 'EXPIRED', true, '1111', null, null, null),
            new CardToken('NEW', 'ACTIVE', false, '2222', null, null, null),
        ]);

        self::assertSame([0], array_keys($payMethods->activeCardTokens()));
        self::assertSame('NEW', $payMethods->activeCardTokens()[0]->value);
    }

    public function testPayMethodsWithoutCardTokens(): void
    {
        self::assertSame([], PayMethods::fromDocument(Document::fromArray(['status' => ['statusCode' => 'SUCCESS']]))->cardTokens);
    }

    public function testCardTokenConstructor(): void
    {
        $token = new CardToken('T', 'ACTIVE', false, '4444********1111', null, null, null);

        self::assertTrue($token->isActive());
        self::assertFalse($token->preferred);
    }
}
