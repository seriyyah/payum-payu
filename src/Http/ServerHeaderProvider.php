<?php

declare(strict_types=1);

namespace PayumPayU\Http;

final readonly class ServerHeaderProvider implements HeaderProviderInterface
{
    /**
     * @param array<string, mixed> $server
     */
    public function __construct(
        private array $server,
    ) {
    }

    public static function fromGlobals(): self
    {
        /** @var array<string, mixed> $server */
        $server = $_SERVER;

        return new self($server);
    }

    public function header(string $name): ?string
    {
        $value = $this->server['HTTP_' . strtoupper(str_replace('-', '_', $name))] ?? null;

        return is_string($value) ? $value : null;
    }
}
