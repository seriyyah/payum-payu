<?php

declare(strict_types=1);

namespace PayumPayU\Json;

use PayumPayU\Json\Exception\DocumentException;

/**
 * Typed, path-aware read access to a decoded JSON object.
 *
 * PayU sends amounts both as integers and as numeric strings, so integer
 * accessors accept either form.
 */
final readonly class Document
{
    /**
     * @param array<string, mixed> $data
     */
    private function __construct(
        private array $data,
        private string $path,
    ) {
    }

    public static function fromJson(string $json): self
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw DocumentException::malformedJson($exception->getMessage());
        }

        return self::fromDecoded($decoded, '$');
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data, '$');
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function string(string $key): string
    {
        $value = $this->required($key);

        if (! is_string($value)) {
            throw DocumentException::unexpectedType($this->pathTo($key), 'a string');
        }

        return $value;
    }

    public function optionalString(string $key): ?string
    {
        return $this->isNull($key) ? null : $this->string($key);
    }

    public function int(string $key): int
    {
        $value = $this->required($key);

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        throw DocumentException::unexpectedType($this->pathTo($key), 'an integer');
    }

    public function optionalInt(string $key): ?int
    {
        return $this->isNull($key) ? null : $this->int($key);
    }

    public function bool(string $key): bool
    {
        $value = $this->required($key);

        if (! is_bool($value)) {
            throw DocumentException::unexpectedType($this->pathTo($key), 'a boolean');
        }

        return $value;
    }

    public function document(string $key): self
    {
        return self::fromDecoded($this->required($key), $this->pathTo($key));
    }

    public function optionalDocument(string $key): ?self
    {
        return $this->isNull($key) ? null : $this->document($key);
    }

    /**
     * @return list<self>
     */
    public function documents(string $key): array
    {
        $value = $this->required($key);

        if (! is_array($value) || ! array_is_list($value)) {
            throw DocumentException::unexpectedType($this->pathTo($key), 'a list');
        }

        $path = $this->pathTo($key);

        return array_map(
            static fn (mixed $item, int $index): self => self::fromDecoded($item, sprintf('%s[%d]', $path, $index)),
            $value,
            array_keys($value),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    private static function fromDecoded(mixed $decoded, string $path): self
    {
        if (! is_array($decoded) || ($decoded !== [] && array_is_list($decoded))) {
            throw DocumentException::notAnObject($path);
        }

        /** @var array<string, mixed> $decoded */
        return new self($decoded, $path);
    }

    private function isNull(string $key): bool
    {
        return ($this->data[$key] ?? null) === null;
    }

    private function required(string $key): mixed
    {
        if (! array_key_exists($key, $this->data)) {
            throw DocumentException::missingField($this->pathTo($key));
        }

        return $this->data[$key];
    }

    private function pathTo(string $key): string
    {
        return $this->path . '.' . $key;
    }
}
