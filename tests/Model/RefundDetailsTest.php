<?php

declare(strict_types=1);

namespace PayumPayU\Tests\Model;

use PayumPayU\Model\RefundDetails;
use PayumPayU\Model\RefundStatus;
use PayumPayU\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class RefundDetailsTest extends TestCase
{
    public function testRoundTripsThroughArray(): void
    {
        $refund = new RefundDetails('R1', RefundStatus::Pending, 500, 'PLN', 'Damaged goods');
        $array = $refund->toArray();

        self::assertSame(['refundId' => 'R1', 'status' => 'PENDING', 'amount' => 500, 'currencyCode' => 'PLN', 'description' => 'Damaged goods'], $array);
        self::assertEquals($refund, RefundDetails::fromArray($array));
    }

    public function testOptionalFieldsMayBeAbsentOrOfWrongType(): void
    {
        $refund = RefundDetails::fromArray(['refundId' => 'R1', 'status' => 'FINALIZED', 'amount' => '500', 'currencyCode' => 1]);

        self::assertNotNull($refund);
        self::assertSame(RefundStatus::Finalized, $refund->status);
        self::assertNull($refund->amount);
        self::assertNull($refund->currencyCode);
        self::assertNull($refund->description);
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('invalidArrays')]
    public function testUnusableArraysYieldNull(array $data): void
    {
        self::assertNull(RefundDetails::fromArray($data));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidArrays(): iterable
    {
        yield 'empty' => [[]];
        yield 'no status' => [['refundId' => 'R1']];
        yield 'no id' => [['status' => 'PENDING']];
        yield 'id not a string' => [['refundId' => 1, 'status' => 'PENDING']];
        yield 'unknown status' => [['refundId' => 'R1', 'status' => 'DONE']];
    }
}
