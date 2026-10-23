<?php

declare(strict_types=1);

namespace PayumPayU\Tests\Double;

use PayumPayU\Http\HeaderProviderInterface;

final readonly class ArrayHeaderProvider implements HeaderProviderInterface
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private array $headers,
    ) {
    }

    public function header(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }
}
