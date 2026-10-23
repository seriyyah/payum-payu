<?php

declare(strict_types=1);

namespace PayumPayU\Tests\Model;

use PayumPayU\Model\PayMethod;
use PayumPayU\Tests\TestCase;

final class PayMethodTest extends TestCase
{
    public function testCardToken(): void
    {
        $payMethod = PayMethod::cardToken('TOK_1');

        self::assertSame(PayMethod::TYPE_CARD_TOKEN, $payMethod->type);
        self::assertSame('TOK_1', $payMethod->value);
        self::assertSame(['payMethod' => ['type' => 'CARD_TOKEN', 'value' => 'TOK_1']], $payMethod->toArray());
    }

    public function testPayByLink(): void
    {
        self::assertSame(['payMethod' => ['type' => 'PBL', 'value' => 'blik']], PayMethod::payByLink('blik')->toArray());
    }

    public function testArbitraryType(): void
    {
        $payMethod = PayMethod::of('INSTALLMENTS', 'ai');

        self::assertSame('INSTALLMENTS', $payMethod->type);
        self::assertSame('ai', $payMethod->value);
    }
}
