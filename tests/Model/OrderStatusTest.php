<?php

declare(strict_types=1);

namespace PayumPayU\Tests\Model;

use PayumPayU\Model\OrderStatus;
use PayumPayU\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class OrderStatusTest extends TestCase
{
    public function testOnlyCompletedAndCanceledAreFinal(): void
    {
        self::assertTrue(OrderStatus::Completed->isFinal());
        self::assertTrue(OrderStatus::Canceled->isFinal());
        self::assertFalse(OrderStatus::New->isFinal());
        self::assertFalse(OrderStatus::Pending->isFinal());
        self::assertFalse(OrderStatus::WaitingForConfirmation->isFinal());
    }

    #[DataProvider('transitions')]
    public function testStatusesOnlyMoveForward(OrderStatus $incoming, ?OrderStatus $current, bool $expected): void
    {
        self::assertSame($expected, $incoming->supersedes($current));
    }

    /**
     * @return iterable<string, array{OrderStatus, OrderStatus|null, bool}>
     */
    public static function transitions(): iterable
    {
        yield 'anything over nothing' => [OrderStatus::New, null, true];
        yield 'pending over new' => [OrderStatus::Pending, OrderStatus::New, true];
        yield 'waiting over pending' => [OrderStatus::WaitingForConfirmation, OrderStatus::Pending, true];
        yield 'completed over waiting' => [OrderStatus::Completed, OrderStatus::WaitingForConfirmation, true];
        yield 'canceled over pending' => [OrderStatus::Canceled, OrderStatus::Pending, true];
        yield 'same status is stale' => [OrderStatus::Pending, OrderStatus::Pending, false];
        yield 'pending after completed is stale' => [OrderStatus::Pending, OrderStatus::Completed, false];
        yield 'new after pending is stale' => [OrderStatus::New, OrderStatus::Pending, false];
        yield 'canceled after completed is stale' => [OrderStatus::Canceled, OrderStatus::Completed, false];
        yield 'completed after canceled is stale' => [OrderStatus::Completed, OrderStatus::Canceled, false];
    }
}
