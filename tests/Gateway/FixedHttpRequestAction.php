<?php

declare(strict_types=1);

namespace PayumPayU\Tests\Gateway;

use Payum\Core\Action\ActionInterface;
use Payum\Core\Request\GetHttpRequest;

/**
 * Stands in for Payum's GetHttpRequestAction so tests control the incoming
 * HTTP body and client IP.
 */
final class FixedHttpRequestAction implements ActionInterface
{
    public function __construct(
        public string $content = '',
        public string $clientIp = '',
    ) {
    }

    public function execute(mixed $request): void
    {
        if (!$request instanceof GetHttpRequest) {
            return;
        }

        $request->method = 'POST';
        $request->content = $this->content;
        $request->clientIp = $this->clientIp;
    }

    public function supports(mixed $request): bool
    {
        return $request instanceof GetHttpRequest;
    }
}
