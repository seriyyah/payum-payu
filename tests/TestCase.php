<?php

declare(strict_types=1);

namespace PayumPayU\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase
{
    /**
     * Runs the callback and returns the exception it throws, so that tests
     * can assert on its message and payload without PHPUnit version
     * specific expectation helpers.
     *
     * @template T of \Throwable
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    protected static function assertThrows(string $class, callable $callback, ?string $message = null): \Throwable
    {
        try {
            $callback();
        } catch (\Throwable $exception) {
            self::assertInstanceOf($class, $exception);

            if ($message !== null) {
                self::assertStringContainsString($message, $exception->getMessage());
            }

            return $exception;
        }

        self::fail(sprintf('Expected %s to be thrown, nothing was.', $class));
    }
}
