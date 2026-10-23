<?php

declare(strict_types=1);

namespace PayumPayU\Tests\Double;

use Nyholm\Psr7\Response;
use PayumPayU\Exception\LogicException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * PSR-18 client that replays queued responses and records every request.
 */
final class RecordingHttpClient implements ClientInterface
{
    /**
     * @var list<ResponseInterface|\Throwable>
     */
    private array $queue = [];

    /**
     * @var list<RequestInterface>
     */
    private array $requests = [];

    public function willRespond(int $status, string $body = '', string $contentType = 'application/json'): self
    {
        $this->queue[] = new Response($status, [
            'Content-Type' => $contentType,
        ], $body);

        return $this;
    }

    /**
     * @param array<string, mixed> $body
     */
    public function willRespondJson(int $status, array $body): self
    {
        return $this->willRespond($status, json_encode($body, JSON_THROW_ON_ERROR));
    }

    public function willThrow(\Throwable $exception): self
    {
        $this->queue[] = $exception;

        return $this;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        $next = array_shift($this->queue)
            ?? throw new LogicException(sprintf('No response queued for %s %s.', $request->getMethod(), $request->getUri()));

        if ($next instanceof \Throwable) {
            throw $next;
        }

        return $next;
    }

    /**
     * @return list<RequestInterface>
     */
    public function requests(): array
    {
        return $this->requests;
    }

    public function request(int $index): RequestInterface
    {
        return $this->requests[$index] ?? throw new LogicException(sprintf('Only %d requests were sent.', count($this->requests)));
    }

    public function lastRequest(): RequestInterface
    {
        $last = end($this->requests);

        return $last === false ? throw new LogicException('No request was sent.') : $last;
    }

    /**
     * @return array<string, mixed>
     */
    public function lastJsonBody(): array
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) $this->lastRequest()->getBody(), true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
