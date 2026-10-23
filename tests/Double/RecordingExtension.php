<?php

declare(strict_types=1);

namespace PayumPayU\Tests\Double;

use Payum\Core\Extension\Context;
use Payum\Core\Extension\ExtensionInterface;

/**
 * Records every request the gateway executes, including sub-requests.
 */
final class RecordingExtension implements ExtensionInterface
{
    /**
     * @var list<object>
     */
    private array $requests = [];

    public function onPreExecute(Context $context): void
    {
        $request = $context->getRequest();

        if (!is_object($request)) {
            return;
        }

        $this->requests[] = $request;
    }

    public function onExecute(Context $context): void
    {
    }

    public function onPostExecute(Context $context): void
    {
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    public function single(string $class): object
    {
        $matches = array_values(array_filter($this->requests, static fn (object $request): bool => $request instanceof $class));

        if (count($matches) !== 1) {
            throw new \LogicException(sprintf('Expected exactly one %s request, recorded %d.', $class, count($matches)));
        }

        return $matches[0];
    }
}
