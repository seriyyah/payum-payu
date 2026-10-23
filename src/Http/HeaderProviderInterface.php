<?php

declare(strict_types=1);

namespace PayumPayU\Http;

/**
 * Reads a header of the HTTP request currently being handled.
 *
 * Payum's GetHttpRequest exposes the body but not the headers, and PayU
 * signs notifications through the OpenPayu-Signature header.
 */
interface HeaderProviderInterface
{
    public function header(string $name): ?string;
}
