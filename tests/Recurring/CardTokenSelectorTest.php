<?php

declare(strict_types=1);

namespace PayumPayU\Tests\Recurring;

use PayumPayU\Recurring\CardTokenSelector;
use PayumPayU\Recurring\Exception\CardTokenNotFoundException;
use PayumPayU\Rest\Response\CardToken;
use PayumPayU\Rest\Response\PayMethods;
use PayumPayU\Tests\TestCase;

final class CardTokenSelectorTest extends TestCase
{
    private CardTokenSelector $selector;

    public function testPrefersThePreferredActiveCard(): void
    {
        $payMethods = new PayMethods([
            self::token('TOK_A', 'ACTIVE', false, '4444********1111'),
            self::token('TOK_B', 'ACTIVE', true, '5555********4444'),
        ]);

        self::assertSame('TOK_B', $this->selector->select($payMethods, null)->value);
    }

    public function testFallsBackToTheFirstActiveCard(): void
    {
        $payMethods = new PayMethods([
            self::token('TOK_OLD', 'EXPIRED', true, '4444********1111'),
            self::token('TOK_A', 'ACTIVE', false, '4444********1111'),
            self::token('TOK_B', 'ACTIVE', false, '5555********4444'),
        ]);

        self::assertSame('TOK_A', $this->selector->select($payMethods, null)->value);
    }

    public function testRestrictsToTheMaskedNumber(): void
    {
        $payMethods = new PayMethods([
            self::token('TOK_A', 'ACTIVE', true, '4444********1111'),
            self::token('TOK_B', 'ACTIVE', false, '5555********4444'),
        ]);

        self::assertSame('TOK_B', $this->selector->select($payMethods, '5555********4444')->value);
    }

    public function testFailsWhenNoActiveCardExists(): void
    {
        self::assertThrows(
            CardTokenNotFoundException::class,
            fn (): CardToken => $this->selector->select(new PayMethods([self::token('TOK_OLD', 'EXPIRED', true, '4444********1111')]), null),
            'The buyer has no active card token stored at PayU.',
        );
    }

    public function testFailsWhenNoCardMatchesTheMaskedNumber(): void
    {
        self::assertThrows(
            CardTokenNotFoundException::class,
            fn (): CardToken => $this->selector->select(new PayMethods([self::token('TOK_A', 'ACTIVE', true, '4444********1111')]), '5555********4444'),
            'The buyer has no active card token stored at PayU for the card 5555********4444.',
        );
    }

    protected function setUp(): void
    {
        $this->selector = new CardTokenSelector();
    }

    private static function token(string $value, string $status, bool $preferred, string $masked): CardToken
    {
        return new CardToken($value, $status, $preferred, $masked, 'VISA', '12', '2030');
    }
}
