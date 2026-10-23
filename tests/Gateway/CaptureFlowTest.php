<?php

declare(strict_types=1);

namespace PayumPayU\Tests\Gateway;

use Payum\Core\Model\Payment;
use Payum\Core\Reply\HttpRedirect;
use Payum\Core\Request\Capture;
use Payum\Core\Request\Sync;
use PayumPayU\Exception\LogicException;
use PayumPayU\Model\Details;
use PayumPayU\Model\Exception\MissingDetailException;
use PayumPayU\Recurring\Exception\CardTokenNotFoundException;
use PayumPayU\Request\CreateOrder;
use PayumPayU\Rest\Exception\ApiException;

final class CaptureFlowTest extends GatewayTestCase
{
    public function testFirstCaptureCreatesTheOrderAndRedirectsTheBuyer(): void
    {
        $this->httpRequest->clientIp = '203.0.113.9';
        $this->queueAccessToken();
        $this->httpClient->willRespondJson(302, [
            'status' => ['statusCode' => 'SUCCESS'],
            'redirectUri' => 'https://merch-prod.snd.payu.com/pay/?orderId=O1',
            'orderId' => 'O1',
            'extOrderId' => 'INV-1',
        ]);
        $details = self::convertedDetails();
        $capture = new Capture($this->captureToken());
        $capture->setModel($details);

        $reply = $this->gateway()->execute($capture, true);

        self::assertInstanceOf(HttpRedirect::class, $reply);
        self::assertSame('https://merch-prod.snd.payu.com/pay/?orderId=O1', $reply->getUrl());
        self::assertSame('O1', $details[Details::ORDER_ID]);
        self::assertSame('NEW', $details[Details::STATUS]);
        self::assertSame('203.0.113.9', $details[Details::CUSTOMER_IP]);
        self::assertSame('https://merch-prod.snd.payu.com/pay/?orderId=O1', $details[Details::REDIRECT_URI]);

        $order = $this->httpClient->lastJsonBody();
        self::assertSame(self::TARGET_URL, $order['continueUrl']);
        self::assertSame(self::NOTIFY_URL, $order['notifyUrl']);
        self::assertSame('203.0.113.9', $order['customerIp']);
        self::assertSame('300746', $order['merchantPosId']);
        self::assertSame(2100, $order['totalAmount']);
        self::assertSame(['email' => 'buyer@example.com', 'extCustomerId' => 'C-1'], $order['buyer']);
        self::assertSame('Bearer tok', $this->httpClient->lastRequest()->getHeaderLine('Authorization'));
    }

    public function testCapturingAPaymentModelConvertsItFirst(): void
    {
        $this->queueAccessToken();
        $this->httpClient->willRespondJson(302, [
            'status' => ['statusCode' => 'SUCCESS'],
            'redirectUri' => 'https://merch-prod.snd.payu.com/pay/?orderId=O1',
            'orderId' => 'O1',
        ]);
        $payment = new Payment();
        $payment->setNumber('INV-7');
        $payment->setDescription('Premium');
        $payment->setClientEmail('buyer@example.com');
        $payment->setClientId('C-7');
        $payment->setTotalAmount(4200);
        $payment->setCurrencyCode('PLN');
        $payment->setDetails([Details::CUSTOMER_IP => '198.51.100.4', Details::FIRST_NAME => 'Jan']);
        $capture = new Capture($this->captureToken());
        $capture->setModel($payment);

        $reply = $this->gateway()->execute($capture, true);

        self::assertInstanceOf(HttpRedirect::class, $reply);
        $details = $payment->getDetails();
        self::assertIsArray($details);
        self::assertSame('O1', $details[Details::ORDER_ID]);
        self::assertSame('INV-7', $details[Details::EXT_ORDER_ID]);
        self::assertSame(4200, $details[Details::TOTAL_AMOUNT]);
        self::assertSame('NEW', $details[Details::STATUS]);
        self::assertSame('Jan', $details[Details::FIRST_NAME]);
        self::assertSame(['email' => 'buyer@example.com', 'firstName' => 'Jan', 'extCustomerId' => 'C-7'], $this->httpClient->lastJsonBody()['buyer']);

        $createOrder = $this->recorder->single(CreateOrder::class);
        self::assertSame($payment, $createOrder->getFirstModel());
        self::assertInstanceOf(\ArrayAccess::class, $createOrder->getModel());
        self::assertNotSame($payment, $createOrder->getModel());
    }

    public function testANewOrderStartsAsNewUnlessAStatusWasSetBefore(): void
    {
        $this->queueAccessToken();
        $this->httpClient->willRespondJson(302, ['status' => ['statusCode' => 'SUCCESS'], 'redirectUri' => 'https://pay', 'orderId' => 'O1']);
        $this->httpClient->willRespondJson(302, ['status' => ['statusCode' => 'SUCCESS'], 'redirectUri' => 'https://pay', 'orderId' => 'O2']);
        $fresh = self::convertedDetails([Details::CUSTOMER_IP => '10.0.0.1']);
        unset($fresh[Details::STATUS]);
        $preset = self::convertedDetails([Details::CUSTOMER_IP => '10.0.0.1', Details::STATUS => 'PENDING']);
        $gateway = $this->gateway();

        foreach ([$fresh, $preset] as $details) {
            $capture = new Capture($this->captureToken());
            $capture->setModel($details);
            $gateway->execute($capture, true);
        }

        self::assertSame('NEW', $fresh[Details::STATUS]);
        self::assertSame('PENDING', $preset[Details::STATUS]);
    }

    public function testACardTokenChargeOfAPaymentModelSynchronisesThePayment(): void
    {
        $this->queueAccessToken();
        $this->httpClient->willRespondJson(200, ['status' => ['statusCode' => 'SUCCESS'], 'orderId' => 'O2']);
        $this->httpClient->willRespondJson(200, ['orders' => [['orderId' => 'O2', 'status' => 'COMPLETED']], 'status' => ['statusCode' => 'SUCCESS']]);
        $payment = new Payment();
        $payment->setNumber('INV-8');
        $payment->setDescription('Premium');
        $payment->setClientEmail('buyer@example.com');
        $payment->setClientId('C-8');
        $payment->setTotalAmount(100);
        $payment->setCurrencyCode('PLN');
        $payment->setDetails([
            Details::CUSTOMER_IP => '10.0.0.1',
            Details::RECURRING => 'STANDARD',
            Details::PAY_METHODS => ['payMethod' => ['type' => 'CARD_TOKEN', 'value' => 'TOK_A']],
        ]);
        $capture = new Capture($this->captureToken());
        $capture->setModel($payment);

        $this->gateway()->execute($capture);

        $details = $payment->getDetails();
        self::assertIsArray($details);
        self::assertSame('COMPLETED', $details[Details::STATUS]);
        $sync = $this->recorder->single(Sync::class);
        self::assertSame($payment, $sync->getFirstModel());
        self::assertInstanceOf(\ArrayAccess::class, $sync->getModel());
        self::assertNotSame($payment, $sync->getModel());
    }

    public function testSecondCaptureSynchronisesTheOrder(): void
    {
        $this->queueAccessToken();
        $this->httpClient->willRespondJson(200, [
            'orders' => [['orderId' => 'O1', 'status' => 'COMPLETED', 'payMethod' => ['type' => 'PBL']]],
            'status' => ['statusCode' => 'SUCCESS'],
        ]);
        $details = self::convertedDetails([Details::ORDER_ID => 'O1', Details::STATUS => 'PENDING']);
        $capture = new Capture($this->captureToken());
        $capture->setModel($details);

        $reply = $this->gateway()->execute($capture, true);

        self::assertNull($reply);
        self::assertSame('COMPLETED', $details[Details::STATUS]);
        self::assertSame('PBL', $details[Details::PAY_METHOD_TYPE]);
        self::assertSame('GET', $this->httpClient->lastRequest()->getMethod());
        self::assertSame('https://secure.snd.payu.com/api/v2_1/orders/O1', (string) $this->httpClient->lastRequest()->getUri());
    }

    public function testCardTokenChargeWithoutRedirectIsSynchronisedImmediately(): void
    {
        $this->httpRequest->clientIp = '203.0.113.9';
        $this->queueAccessToken();
        $this->httpClient->willRespondJson(200, [
            'status' => ['statusCode' => 'SUCCESS'],
            'orderId' => 'O2',
            'payMethods' => ['payMethod' => ['card' => ['number' => '4444********1111']]],
        ]);
        $this->httpClient->willRespondJson(200, [
            'orders' => [['orderId' => 'O2', 'status' => 'COMPLETED', 'payMethod' => ['type' => 'CARD_TOKEN']]],
            'status' => ['statusCode' => 'SUCCESS'],
        ]);
        $details = self::convertedDetails([
            Details::CUSTOMER_IP => '10.0.0.1',
            Details::RECURRING => 'STANDARD',
            Details::PAY_METHODS => ['payMethod' => ['type' => 'CARD_TOKEN', 'value' => 'TOK_A']],
        ]);
        $capture = new Capture($this->captureToken());
        $capture->setModel($details);

        $reply = $this->gateway()->execute($capture, true);

        self::assertNull($reply);
        self::assertSame('O2', $details[Details::ORDER_ID]);
        self::assertSame('COMPLETED', $details[Details::STATUS]);
        self::assertSame('4444********1111', $details[Details::CARD_MASKED_NUMBER]);
        self::assertSame('CARD_TOKEN', $details[Details::PAY_METHOD_TYPE]);
        self::assertArrayNotHasKey(Details::REDIRECT_URI, $details->getArrayCopy());
        self::assertCount(3, $this->httpClient->requests());
        /** @var array<string, mixed> $order */
        $order = json_decode((string) $this->httpClient->request(1)->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('10.0.0.1', $order['customerIp']);
    }

    public function testStandardRecurringChargeLooksUpTheStoredCardToken(): void
    {
        $this->queueAccessToken();
        $this->httpClient->willRespondJson(200, [
            'cardTokens' => [
                ['value' => 'TOK_A', 'status' => 'ACTIVE', 'preferred' => true, 'cardNumberMasked' => '4444********1111'],
                ['value' => 'TOK_B', 'status' => 'ACTIVE', 'preferred' => false, 'cardNumberMasked' => '5555********4444'],
            ],
            'status' => ['statusCode' => 'SUCCESS'],
        ]);
        $this->queueAccessToken();
        $this->httpClient->willRespondJson(200, ['status' => ['statusCode' => 'SUCCESS'], 'orderId' => 'O3']);
        $this->httpClient->willRespondJson(200, ['orders' => [['orderId' => 'O3', 'status' => 'PENDING']], 'status' => ['statusCode' => 'SUCCESS']]);
        $details = self::convertedDetails([
            Details::CUSTOMER_IP => '10.0.0.1',
            Details::RECURRING => 'STANDARD',
            Details::CARD_MASKED_NUMBER => '5555********4444',
        ]);
        $capture = new Capture($this->captureToken());
        $capture->setModel($details);

        $this->gateway()->execute($capture);

        self::assertCount(5, $this->httpClient->requests());
        self::assertStringContainsString('grant_type=trusted_merchant', (string) $this->httpClient->request(0)->getBody());
        self::assertStringContainsString('email=buyer%40example.com&ext_customer_id=C-1', (string) $this->httpClient->request(0)->getBody());
        self::assertSame('https://secure.snd.payu.com/api/v2_1/paymethods', (string) $this->httpClient->request(1)->getUri());
        self::assertStringContainsString('grant_type=client_credentials', (string) $this->httpClient->request(2)->getBody());
        /** @var array<string, mixed> $order */
        $order = json_decode((string) $this->httpClient->request(3)->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('STANDARD', $order['recurring']);
        self::assertSame(['payMethod' => ['type' => 'CARD_TOKEN', 'value' => 'TOK_B']], $order['payMethods']);
        self::assertSame('PENDING', $details[Details::STATUS]);
    }

    public function testStandardRecurringChargeRemembersTheChosenCard(): void
    {
        $this->queueAccessToken();
        $this->httpClient->willRespondJson(200, [
            'cardTokens' => [['value' => 'TOK_A', 'status' => 'ACTIVE', 'preferred' => true, 'cardNumberMasked' => '4444********1111']],
            'status' => ['statusCode' => 'SUCCESS'],
        ]);
        $this->queueAccessToken();
        $this->httpClient->willRespondJson(200, ['status' => ['statusCode' => 'SUCCESS'], 'orderId' => 'O4']);
        $this->httpClient->willRespondJson(200, ['orders' => [['orderId' => 'O4', 'status' => 'COMPLETED']], 'status' => ['statusCode' => 'SUCCESS']]);
        $details = self::convertedDetails([Details::CUSTOMER_IP => '10.0.0.1', Details::RECURRING => 'STANDARD']);
        $capture = new Capture($this->captureToken());
        $capture->setModel($details);

        $this->gateway()->execute($capture);

        self::assertSame('4444********1111', $details[Details::CARD_MASKED_NUMBER]);
        self::assertSame(['payMethod' => ['type' => 'CARD_TOKEN', 'value' => 'TOK_A']], $details[Details::PAY_METHODS]);
    }

    public function testStandardRecurringChargeWithoutAStoredCardFails(): void
    {
        $this->queueAccessToken();
        $this->httpClient->willRespondJson(200, ['cardTokens' => [], 'status' => ['statusCode' => 'SUCCESS']]);
        $capture = new Capture($this->captureToken());
        $capture->setModel(self::convertedDetails([Details::CUSTOMER_IP => '10.0.0.1', Details::RECURRING => 'STANDARD']));
        $gateway = $this->gateway();

        self::assertThrows(CardTokenNotFoundException::class, static fn () => $gateway->execute($capture), 'no active card token');
    }

    public function testStandardRecurringChargeNeedsTheBuyerIdentity(): void
    {
        $details = self::convertedDetails([Details::CUSTOMER_IP => '10.0.0.1', Details::RECURRING => 'STANDARD']);
        unset($details[Details::CLIENT_EMAIL]);
        $capture = new Capture($this->captureToken());
        $capture->setModel($details);
        $gateway = $this->gateway();

        self::assertThrows(
            MissingDetailException::class,
            static fn () => $gateway->execute($capture),
            'The payment detail "clientEmail" is required: needed to look up the buyer card tokens',
        );
        self::assertSame([], $this->httpClient->requests());
    }

    public function testStandardRecurringChargeNeedsTheBuyerExternalId(): void
    {
        $details = self::convertedDetails([Details::CUSTOMER_IP => '10.0.0.1', Details::RECURRING => 'STANDARD']);
        unset($details[Details::CLIENT_ID]);
        $capture = new Capture($this->captureToken());
        $capture->setModel($details);
        $gateway = $this->gateway();

        self::assertThrows(MissingDetailException::class, static fn () => $gateway->execute($capture), 'The payment detail "clientId" is required');
    }

    public function testCaptureWithoutATokenIsRejected(): void
    {
        $capture = new Capture(self::convertedDetails([Details::CUSTOMER_IP => '10.0.0.1']));
        $gateway = $this->gateway();

        self::assertThrows(LogicException::class, static fn () => $gateway->execute($capture), 'CreateOrder needs a capture token');
    }

    public function testAnUnknownCustomerIpIsReported(): void
    {
        $details = self::convertedDetails();
        $capture = new Capture($this->captureToken());
        $capture->setModel($details);
        $gateway = $this->gateway();

        self::assertThrows(MissingDetailException::class, static fn () => $gateway->execute($capture), 'The payment detail "customerIp" is required');
        self::assertSame([], $this->httpClient->requests());
        self::assertArrayNotHasKey(Details::CUSTOMER_IP, $details->getArrayCopy());
    }

    public function testPayURejectionsPropagate(): void
    {
        $this->queueAccessToken();
        $this->httpClient->willRespondJson(400, ['status' => ['statusCode' => 'ERROR_VALUE_INVALID', 'statusDesc' => 'Bad amount']]);
        $capture = new Capture($this->captureToken());
        $capture->setModel(self::convertedDetails([Details::CUSTOMER_IP => '10.0.0.1']));
        $gateway = $this->gateway();

        self::assertThrows(ApiException::class, static fn () => $gateway->execute($capture), 'ERROR_VALUE_INVALID (HTTP 400): Bad amount');
    }

    public function testSyncWithoutAnOrderDoesNothing(): void
    {
        $details = self::convertedDetails();

        $this->gateway()->execute(new Sync($details));

        self::assertSame([], $this->httpClient->requests());
        self::assertSame('NEW', $details[Details::STATUS]);
    }
}
