<?php

declare(strict_types=1);

namespace PayumPayU\Bridge\Symfony;

use PayumPayU\Http\HeaderProviderInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class RequestStackHeaderProvider implements HeaderProviderInterface
{
    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    public function header(string $name): ?string
    {
        return $this->requestStack->getCurrentRequest()?->headers->get($name);
    }
}
