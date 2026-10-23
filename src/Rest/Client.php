<?php

declare(strict_types=1);

namespace PayumPayU\Rest;

use PayumPayU\Json\Document;
use PayumPayU\Json\Exception\DocumentException;
use PayumPayU\Rest\Exception\ApiException;
use PayumPayU\Rest\Exception\InvalidResponseException;
use PayumPayU\Rest\OAuth\Grant;
use PayumPayU\Rest\OAuth\TokenProviderInterface;
use PayumPayU\Rest\Response\OrderCreated;
use PayumPayU\Rest\Response\OrderRetrieved;
use PayumPayU\Rest\Response\PayMethods;
use PayumPayU\Rest\Response\RefundCreated;
use PayumPayU\Rest\Response\Status;
use Psr\Http\Message\ResponseInterface;

final readonly class Client implements ClientInterface
{
    private const ORDERS_PATH = '/api/v2_1/orders';

    private const PAY_METHODS_PATH = '/api/v2_1/paymethods';

    public function __construct(
        private Transport $transport,
        private TokenProviderInterface $tokenProvider,
        private Configuration $configuration,
    ) {
    }

    /**
     * @param array<string, mixed> $order
     */
    public function createOrder(array $order): OrderCreated
    {
        $document = $this->call('order creation', 'POST', self::ORDERS_PATH, Grant::clientCredentials(), $order);

        return $this->interpret('order creation', static fn (): OrderCreated => OrderCreated::fromDocument($document));
    }

    public function retrieveOrder(string $orderId): OrderRetrieved
    {
        $document = $this->call('order retrieval', 'GET', $this->orderPath($orderId), Grant::clientCredentials(), null);

        return $this->interpret('order retrieval', static fn (): OrderRetrieved => OrderRetrieved::fromDocument($document));
    }

    public function cancelOrder(string $orderId): void
    {
        $this->call('order cancellation', 'DELETE', $this->orderPath($orderId), Grant::clientCredentials(), null);
    }

    public function createRefund(string $orderId, string $description, ?int $amount): RefundCreated
    {
        $refund = [
            'description' => $description,
        ];

        if ($amount !== null) {
            $refund['amount'] = $amount;
        }

        $document = $this->call('refund creation', 'POST', $this->orderPath($orderId) . '/refunds', Grant::clientCredentials(), [
            'refund' => $refund,
        ]);

        return $this->interpret('refund creation', static fn (): RefundCreated => RefundCreated::fromDocument($document));
    }

    public function retrievePayMethods(string $buyerEmail, string $extCustomerId): PayMethods
    {
        $grant = Grant::trustedMerchant($buyerEmail, $extCustomerId);
        $document = $this->call('pay methods retrieval', 'GET', self::PAY_METHODS_PATH, $grant, null);

        return $this->interpret('pay methods retrieval', static fn (): PayMethods => PayMethods::fromDocument($document));
    }

    private function orderPath(string $orderId): string
    {
        return self::ORDERS_PATH . '/' . rawurlencode($orderId);
    }

    /**
     * @param array<string, mixed>|null $body
     */
    private function call(string $operation, string $method, string $path, Grant $grant, ?array $body): Document
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->tokenProvider->token($grant)->value,
            'Accept' => 'application/json',
        ];

        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
        }

        $response = $this->transport->send(
            $method,
            $this->configuration->environment->baseUri() . $path,
            $headers,
            $body === null ? null : json_encode($body, JSON_THROW_ON_ERROR),
        );

        return $this->decode($operation, $response);
    }

    private function decode(string $operation, ResponseInterface $response): Document
    {
        $httpStatus = $response->getStatusCode();
        $document = $this->interpret($operation, static fn (): Document => Document::fromJson((string) $response->getBody()));
        $status = $document->has('status')
            ? $this->interpret($operation, static fn (): Status => Status::fromDocument($document->document('status')))
            : null;

        if ($status !== null && ! $status->isAccepted()) {
            throw ApiException::fromStatus($operation, $httpStatus, $status);
        }

        if ($status === null && ($httpStatus < 200 || $httpStatus >= 400)) {
            throw ApiException::fromHttpStatus($operation, $httpStatus);
        }

        return $document;
    }

    /**
     * @template T
     *
     * @param \Closure(): T $read
     *
     * @return T
     */
    private function interpret(string $operation, \Closure $read): mixed
    {
        try {
            return $read();
        } catch (DocumentException $exception) {
            throw InvalidResponseException::fromDocumentException($exception, $operation);
        }
    }
}
