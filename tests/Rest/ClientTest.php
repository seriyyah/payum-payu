<?php

declare(strict_types=1);

namespace PayumPayU\Tests\Rest;

use Nyholm\Psr7\Factory\Psr17Factory;
use PayumPayU\Model\OrderStatus;
use PayumPayU\Model\RefundStatus;
use PayumPayU\Rest\Client;
use PayumPayU\Rest\Configuration;
use PayumPayU\Rest\Environment;
use PayumPayU\Rest\Exception\ApiException;
use PayumPayU\Rest\Exception\InvalidResponseException;
use PayumPayU\Rest\OAuth\AccessToken;
use PayumPayU\Rest\OAuth\Grant;
use PayumPayU\Rest\OAuth\TokenProviderInterface;
use PayumPayU\Rest\Response\OrderCreated;
use PayumPayU\Rest\Response\OrderRetrieved;
use PayumPayU\Rest\Transport;
use PayumPayU\Tests\Double\RecordingHttpClient;
use PayumPayU\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ClientTest extends TestCase
{
    private RecordingHttpClient $httpClient;

    private Client $client;

    public function testCreatesAnOrderAndAcceptsTheRedirectResponse(): void
    {
        $this->httpClient->willRespondJson(302, [
            'status' => ['statusCode' => 'SUCCESS'],
            'redirectUri' => 'https://merch-prod.snd.payu.com/pay/?orderId=O1',
            'orderId' => 'O1',
            'extOrderId' => 'INV-1',
        ]);

        $created = $this->client->createOrder(['extOrderId' => 'INV-1', 'totalAmount' => 2100]);

        self::assertSame('O1', $created->orderId);
        self::assertSame('https://merch-prod.snd.payu.com/pay/?orderId=O1', $created->redirectUri);
        $request = $this->httpClient->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://secure.snd.payu.com/api/v2_1/orders', (string) $request->getUri());
        self::assertSame('Bearer bearer-client_credentials::', $request->getHeaderLine('Authorization'));
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
        self::assertSame(['extOrderId' => 'INV-1', 'totalAmount' => 2100], $this->httpClient->lastJsonBody());
    }

    public function testRetrievesAnOrder(): void
    {
        $this->httpClient->willRespondJson(200, [
            'orders' => [['orderId' => 'O 1', 'status' => 'COMPLETED', 'payMethod' => ['type' => 'PBL']]],
            'status' => ['statusCode' => 'SUCCESS'],
        ]);

        $retrieved = $this->client->retrieveOrder('O 1');

        self::assertSame(OrderStatus::Completed, $retrieved->status);
        self::assertSame('PBL', $retrieved->payMethodType);
        $request = $this->httpClient->lastRequest();
        self::assertSame('GET', $request->getMethod());
        self::assertSame('https://secure.snd.payu.com/api/v2_1/orders/O%201', (string) $request->getUri());
        self::assertFalse($request->hasHeader('Content-Type'));
        self::assertSame('', (string) $request->getBody());
    }

    public function testCancelsAnOrder(): void
    {
        $this->httpClient->willRespondJson(200, ['orderId' => 'O1', 'status' => ['statusCode' => 'SUCCESS']]);

        $this->client->cancelOrder('O1');

        self::assertSame('DELETE', $this->httpClient->lastRequest()->getMethod());
        self::assertSame('https://secure.snd.payu.com/api/v2_1/orders/O1', (string) $this->httpClient->lastRequest()->getUri());
    }

    public function testCreatesAFullRefund(): void
    {
        $this->httpClient->willRespondJson(200, [
            'orderId' => 'O1',
            'refund' => ['refundId' => 'R1', 'status' => 'PENDING', 'amount' => 2100, 'currencyCode' => 'PLN', 'description' => 'Refund'],
            'status' => ['statusCode' => 'SUCCESS'],
        ]);

        $refund = $this->client->createRefund('O1', 'Refund', null);

        self::assertSame(RefundStatus::Pending, $refund->refund->status);
        self::assertSame('https://secure.snd.payu.com/api/v2_1/orders/O1/refunds', (string) $this->httpClient->lastRequest()->getUri());
        self::assertSame(['refund' => ['description' => 'Refund']], $this->httpClient->lastJsonBody());
    }

    public function testCreatesAPartialRefund(): void
    {
        $this->httpClient->willRespondJson(200, [
            'orderId' => 'O1',
            'refund' => ['refundId' => 'R1', 'status' => 'PENDING'],
            'status' => ['statusCode' => 'SUCCESS'],
        ]);

        $this->client->createRefund('O1', 'Damaged goods', 500);

        self::assertSame(['refund' => ['description' => 'Damaged goods', 'amount' => 500]], $this->httpClient->lastJsonBody());
    }

    public function testRetrievesPayMethodsOnBehalfOfTheBuyer(): void
    {
        $this->httpClient->willRespondJson(200, ['cardTokens' => [], 'status' => ['statusCode' => 'SUCCESS']]);

        $payMethods = $this->client->retrievePayMethods('buyer@example.com', 'C-1');

        self::assertSame([], $payMethods->cardTokens);
        self::assertSame('https://secure.snd.payu.com/api/v2_1/paymethods', (string) $this->httpClient->lastRequest()->getUri());
        self::assertSame('Bearer bearer-trusted_merchant:buyer@example.com:C-1', $this->httpClient->lastRequest()->getHeaderLine('Authorization'));
    }

    public function testPayUStatusErrorsBecomeApiExceptions(): void
    {
        $this->httpClient->willRespondJson(400, ['status' => ['statusCode' => 'ERROR_VALUE_INVALID', 'statusDesc' => 'totalAmount must be positive']]);

        try {
            $this->client->createOrder([]);
            self::fail('An ApiException was expected.');
        } catch (ApiException $exception) {
            self::assertSame('PayU rejected order creation with ERROR_VALUE_INVALID (HTTP 400): totalAmount must be positive', $exception->getMessage());
            self::assertSame(400, $exception->httpStatusCode);
            self::assertSame('ERROR_VALUE_INVALID', $exception->status?->statusCode);
        }
    }

    public function testStatusErrorsWithoutDescription(): void
    {
        $this->httpClient->willRespondJson(401, ['status' => ['statusCode' => 'UNAUTHORIZED']]);

        self::assertThrows(
            ApiException::class,
            fn (): OrderRetrieved => $this->client->retrieveOrder('O1'),
            'PayU rejected order retrieval with UNAUTHORIZED (HTTP 401): no description',
        );
    }

    public function testHttpErrorsWithoutAStatusObjectBecomeApiExceptions(): void
    {
        $this->httpClient->willRespondJson(503, ['error' => 'maintenance']);

        try {
            $this->client->cancelOrder('O1');
            self::fail('An ApiException was expected.');
        } catch (ApiException $exception) {
            self::assertSame('PayU rejected order cancellation with HTTP 503.', $exception->getMessage());
            self::assertSame(503, $exception->httpStatusCode);
            self::assertNull($exception->status);
        }
    }

    #[DataProvider('httpCodesWithoutStatus')]
    public function testHttpCodesDecideWhenNoStatusObjectIsPresent(int $httpStatus, bool $rejected): void
    {
        $this->httpClient->willRespondJson($httpStatus, ['orderId' => 'O1']);

        if ($rejected) {
            self::assertThrows(ApiException::class, fn () => $this->client->cancelOrder('O1'), sprintf('HTTP %d', $httpStatus));

            return;
        }

        $this->client->cancelOrder('O1');
        self::assertCount(1, $this->httpClient->requests());
    }

    /**
     * @return iterable<string, array{int, bool}>
     */
    public static function httpCodesWithoutStatus(): iterable
    {
        yield 'informational' => [199, true];
        yield 'ok' => [200, false];
        yield 'redirect' => [302, false];
        yield 'last success-ish' => [399, false];
        yield 'bad request' => [400, true];
        yield 'server error' => [500, true];
    }

    public function testAnAcceptedStatusWinsOverTheHttpCode(): void
    {
        $this->httpClient->willRespondJson(500, ['orderId' => 'O1', 'status' => ['statusCode' => 'SUCCESS']]);

        $this->client->cancelOrder('O1');

        self::assertCount(1, $this->httpClient->requests());
    }

    public function testNonJsonBodiesAreReported(): void
    {
        $this->httpClient->willRespond(200, '<html>maintenance</html>', 'text/html');

        self::assertThrows(
            InvalidResponseException::class,
            fn (): OrderRetrieved => $this->client->retrieveOrder('O1'),
            'Unexpected PayU response to order retrieval: The JSON document is malformed',
        );
    }

    public function testMalformedStatusObjectsAreReported(): void
    {
        $this->httpClient->willRespondJson(200, ['status' => ['code' => 1]]);

        self::assertThrows(
            InvalidResponseException::class,
            fn () => $this->client->cancelOrder('O1'),
            'Unexpected PayU response to order cancellation: The field "$.status.statusCode" is missing.',
        );
    }

    public function testIncompleteSuccessBodiesAreReported(): void
    {
        $this->httpClient->willRespondJson(201, ['status' => ['statusCode' => 'SUCCESS']]);

        self::assertThrows(
            InvalidResponseException::class,
            fn (): OrderCreated => $this->client->createOrder([]),
            'Unexpected PayU response to order creation: The field "$.orderId" is missing.',
        );
    }

    protected function setUp(): void
    {
        $this->httpClient = new RecordingHttpClient();
        $tokenProvider = new class () implements TokenProviderInterface {
            public function token(Grant $grant): AccessToken
            {
                return new AccessToken('bearer-' . $grant->cacheKey(), new \DateTimeImmutable('+1 hour'));
            }
        };
        $this->client = new Client(
            new Transport($this->httpClient, new Psr17Factory(), new Psr17Factory()),
            $tokenProvider,
            new Configuration(Environment::Sandbox, '300746', 'key', 'client', 'secret'),
        );
    }
}
