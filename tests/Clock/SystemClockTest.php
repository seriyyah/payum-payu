<?php

declare(strict_types=1);

namespace PayumPayU\Tests\Clock;

use PayumPayU\Clock\SystemClock;
use PayumPayU\Tests\TestCase;

final class SystemClockTest extends TestCase
{
    public function testReturnsTheCurrentTime(): void
    {
        $before = new \DateTimeImmutable();
        $now = (new SystemClock())->now();
        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before, $now);
        self::assertLessThanOrEqual($after, $now);
    }
}
