<?php

declare(strict_types=1);

namespace PayumPayU\Tests\Bridge\Symfony;

use PayumPayU\Bridge\Symfony\RequestStackHeaderProvider;
use PayumPayU\Tests\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class RequestStackHeaderProviderTest extends TestCase
{
    public function testReadsHeadersOfTheCurrentRequest(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/notify', 'POST', server: ['HTTP_OPENPAYU_SIGNATURE' => 'sender=checkout;signature=abc']));

        $provider = new RequestStackHeaderProvider($requestStack);

        self::assertSame('sender=checkout;signature=abc', $provider->header('OpenPayu-Signature'));
        self::assertNull($provider->header('X-OpenPayU-Signature'));
    }

    public function testReturnsNullOutsideOfARequest(): void
    {
        self::assertNull((new RequestStackHeaderProvider(new RequestStack()))->header('OpenPayu-Signature'));
    }
}
