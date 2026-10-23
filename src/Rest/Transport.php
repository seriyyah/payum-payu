<?php

declare(strict_types=1);

namespace PayumPayU\Rest;

use PayumPayU\Rest\Exception\TransportException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Builds and sends PSR-7 requests through a PSR-18 client.
 *
 * The client must not follow redirects: PayU answers an order creation with
 * HTTP 302 whose body carries the JSON result.
 */
final readonly class Transport
{
    public function __construct(
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
    ) {
    }

    /**
     * @param array<string, string> $headers
     */
    public function send(string $method, string $uri, array $headers, ?string $body): ResponseInterface
    {
        $request = $this->requestFactory->createRequest($method, $uri);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        if ($body !== null) {
            $request = $request->withBody($this->streamFactory->createStream($body));
        }

        return $this->deliver($request);
    }

    private function deliver(RequestInterface $request): ResponseInterface
    {
        try {
            return $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $exception) {
            throw TransportException::fromClientException($exception, $request);
        }
    }
}
