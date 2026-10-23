<?php

declare(strict_types=1);

namespace PayumPayU\Tests\Gateway;

use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Model\Payment;
use Payum\Core\Request\Cancel;
use Payum\Core\Request\Convert;
use Payum\Core\Request\GetHumanStatus;
use Payum\Core\Request\Refund;
use PayumPayU\Action\ConvertPaymentAction;
use PayumPayU\Exception\LogicException;
use PayumPayU\Model\Details;
use PayumPayU\Model\Exception\MissingDetailException;
use PayumPayU\Request\RefundOrder;
use PayumPayU\Request\RetrievePayMethods;
use PayumPayU\Rest\Response\CardToken;
use PayumPayU\Rest\Response\PayMethods;
use PHPUnit\Framework\Attributes\DataProvider;

final class StatusRefundCancelTest extends GatewayTestCase
{
    /**
     * @param array<string, mixed> $details
     */
    #[DataProvider('statuses')]
    public function testHumanStatusMapping(array $details, string $expected): void
    {
        $status = new GetHumanStatus(new \ArrayObject($details));
        $status->markPayedout();

        $this->gateway()->execute($status);

        self::assertSame($expected, $status->getValue());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function statuses(): iterable
    {
        yield 'no status yet' => [[], GetHumanStatus::STATUS_NEW];
        yield 'new' => [[Details::STATUS => 'NEW'], GetHumanStatus::STATUS_NEW];
        yield 'pending' => [[Details::STATUS => 'PENDING'], GetHumanStatus::STATUS_PENDING];
        yield 'waiting for confirmation' => [[Details::STATUS => 'WAITING_FOR_CONFIRMATION'], GetHumanStatus::STATUS_PENDING];
        yield 'completed' => [[Details::STATUS => 'COMPLETED'], GetHumanStatus::STATUS_CAPTURED];
        yield 'canceled' => [[Details::STATUS => 'CANCELED'], GetHumanStatus::STATUS_CANCELED];
        yield 'refund finalized wins over completed' => [
            [Details::STATUS => 'COMPLETED', Details::REFUND => ['refundId' => 'R1', 'status' => 'FINALIZED']],
            GetHumanStatus::STATUS_REFUNDED,
        ];

        yield 'pending refund keeps the order status' => [
            [Details::STATUS => 'COMPLETED', Details::REFUND => ['refundId' => 'R1', 'status' => 'PENDING']],
            GetHumanStatus::STATUS_CAPTURED,
        ];

        yield 'status from another integration' => [[Details::STATUS => 'REFUND_FINALIZED'], GetHumanStatus::STATUS_UNKNOWN];
    }

    public function testStatusOfAnUnsupportedModelIsNotHandled(): void
    {
        $gateway = $this->gateway();

        self::assertThrows(RequestNotSupportedException::class, static fn () => $gateway->execute(new GetHumanStatus(new \stdClass())));
    }

    public function testRefundsTheWholeOrderByDefault(): void
    {
        $this->queueAccessToken();
        $this->httpClient->willRespondJson(200, [
            'orderId' => 'O1',
            'refund' => ['refundId' => 'R1', 'status' => 'PENDING', 'amount' => 2100, 'currencyCode' => 'PLN', 'description' => 'Refund'],
            'status' => ['statusCode' => 'SUCCESS'],
        ]);
        $details = self::convertedDetails([Details::ORDER_ID => 'O1', Details::STATUS => 'COMPLETED']);

        $this->gateway()->execute(new Refund($details));

        self::assertSame(['refund' => ['description' => 'Refund']], $this->httpClient->lastJsonBody());
        self::assertSame('https://secure.snd.payu.com/api/v2_1/orders/O1/refunds', (string) $this->httpClient->lastRequest()->getUri());
        self::assertSame(
            ['refundId' => 'R1', 'status' => 'PENDING', 'amount' => 2100, 'currencyCode' => 'PLN', 'description' => 'Refund'],
            $details[Details::REFUND],
        );
        self::assertSame('COMPLETED', $details[Details::STATUS]);
    }

    public function testRefundsAPaymentModel(): void
    {
        $this->queueAccessToken();
        $this->httpClient->willRespondJson(200, [
            'orderId' => 'O1',
            'refund' => ['refundId' => 'R1', 'status' => 'PENDING'],
            'status' => ['statusCode' => 'SUCCESS'],
        ]);
        $payment = new Payment();
        $payment->setDetails([Details::ORDER_ID => 'O1', Details::STATUS => 'COMPLETED']);

        $this->gateway()->execute(new Refund($payment));

        $details = $payment->getDetails();
        self::assertIsArray($details);
        self::assertIsArray($details[Details::REFUND]);
        self::assertSame('R1', $details[Details::REFUND]['refundId']);
        $refundOrder = $this->recorder->single(RefundOrder::class);
        self::assertSame($payment, $refundOrder->getFirstModel());
        self::assertInstanceOf(\ArrayAccess::class, $refundOrder->getModel());
        self::assertNotSame($payment, $refundOrder->getModel());
    }

    public function testRefundsAPartialAmountWithADescription(): void
    {
        $this->queueAccessToken();
        $this->httpClient->willRespondJson(200, [
            'orderId' => 'O1',
            'refund' => ['refundId' => 'R2', 'status' => 'PENDING', 'amount' => 500],
            'status' => ['statusCode' => 'SUCCESS'],
        ]);
        $details = self::convertedDetails([
            Details::ORDER_ID => 'O1',
            Details::REFUND_AMOUNT => 500,
            Details::REFUND_DESCRIPTION => 'Damaged goods',
        ]);

        $this->gateway()->execute(new Refund($details));

        self::assertSame(['refund' => ['description' => 'Damaged goods', 'amount' => 500]], $this->httpClient->lastJsonBody());
    }

    public function testRefundNeedsAnOrder(): void
    {
        $gateway = $this->gateway();

        self::assertThrows(
            MissingDetailException::class,
            static fn () => $gateway->execute(new Refund(self::convertedDetails())),
            'The payment detail "orderId" is required',
        );
    }

    public function testCancelsTheOrder(): void
    {
        $this->queueAccessToken();
        $this->httpClient->willRespondJson(200, ['orderId' => 'O1', 'status' => ['statusCode' => 'SUCCESS']]);
        $details = self::convertedDetails([Details::ORDER_ID => 'O1', Details::STATUS => 'PENDING']);

        $this->gateway()->execute(new Cancel($details));

        self::assertSame('DELETE', $this->httpClient->lastRequest()->getMethod());
        self::assertSame('https://secure.snd.payu.com/api/v2_1/orders/O1', (string) $this->httpClient->lastRequest()->getUri());
        self::assertSame('CANCELED', $details[Details::STATUS]);
    }

    public function testCancelNeedsAnOrder(): void
    {
        $gateway = $this->gateway();

        self::assertThrows(MissingDetailException::class, static fn () => $gateway->execute(new Cancel(self::convertedDetails())));
    }

    public function testRetrievesTheBuyerPayMethods(): void
    {
        $this->queueAccessToken();
        $this->httpClient->willRespondJson(200, [
            'cardTokens' => [['value' => 'TOK', 'status' => 'ACTIVE', 'preferred' => true, 'cardNumberMasked' => '4444********1111']],
            'status' => ['statusCode' => 'SUCCESS'],
        ]);
        $request = new RetrievePayMethods('buyer@example.com', 'C-1');

        self::assertThrows(LogicException::class, static fn (): PayMethods => $request->result(), 'has not been executed');

        $this->gateway()->execute($request);

        self::assertSame(['TOK'], array_map(static fn (CardToken $token): string => $token->value, $request->result()->cardTokens));
        self::assertStringContainsString('grant_type=trusted_merchant', (string) $this->httpClient->request(0)->getBody());
    }

    public function testConvertsAPaymentToDetails(): void
    {
        $payment = new Payment();
        $payment->setNumber('INV-3');
        $payment->setDescription('Premium');
        $payment->setClientEmail('buyer@example.com');
        $payment->setClientId('C-3');
        $payment->setTotalAmount(999);
        $payment->setCurrencyCode('EUR');
        $payment->setDetails([Details::LANGUAGE => 'en', Details::STATUS => 'PENDING']);
        $convert = new Convert($payment, 'array', $this->captureToken());

        $this->gateway()->execute($convert);

        self::assertSame([
            Details::LANGUAGE => 'en',
            Details::STATUS => 'PENDING',
            Details::TOTAL_AMOUNT => 999,
            Details::CURRENCY_CODE => 'EUR',
            Details::EXT_ORDER_ID => 'INV-3',
            Details::DESCRIPTION => 'Premium',
            Details::CLIENT_EMAIL => 'buyer@example.com',
            Details::CLIENT_ID => 'C-3',
        ], $convert->getResult());
    }

    public function testOnlyConvertsPaymentsToArrays(): void
    {
        $gateway = $this->gateway();
        $action = new ConvertPaymentAction();

        self::assertFalse($action->supports(new Convert(new Payment(), 'json')));
        self::assertFalse($action->supports(new Convert(new \stdClass(), 'array')));
        self::assertThrows(RequestNotSupportedException::class, static fn () => $gateway->execute(new Convert(new Payment(), 'json')));
        self::assertThrows(RequestNotSupportedException::class, static fn () => $action->execute(new Convert(new \stdClass(), 'array')));
    }
}
