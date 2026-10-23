<?php

declare(strict_types=1);

namespace PayumPayU\Tests\Order;

use PayumPayU\Model\Details;
use PayumPayU\Model\Exception\MissingDetailException;
use PayumPayU\Order\PayloadFactory;
use PayumPayU\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class PayloadFactoryTest extends TestCase
{
    private const CONTINUE_URL = 'https://shop.example/payment/done?payum_token=abc';
    private const NOTIFY_URL = 'https://shop.example/payment/notify/xyz';

    public function testBuildsAMinimalOrder(): void
    {
        $payload = (new PayloadFactory())->create(new Details(new \ArrayObject(self::convertedDetails())), '300746', self::CONTINUE_URL, self::NOTIFY_URL);

        self::assertSame([
            'continueUrl' => self::CONTINUE_URL,
            'notifyUrl' => self::NOTIFY_URL,
            'customerIp' => '10.0.0.7',
            'merchantPosId' => '300746',
            'description' => 'Insurance premium',
            'currencyCode' => 'PLN',
            'totalAmount' => 2100,
            'extOrderId' => 'INV-1',
            'products' => [['name' => 'Insurance premium', 'unitPrice' => 2100, 'quantity' => 1]],
            'buyer' => ['email' => 'buyer@example.com', 'extCustomerId' => 'C-1'],
        ], $payload);
    }

    public function testBuildsAFullOrder(): void
    {
        $details = new Details(new \ArrayObject(self::convertedDetails() + [
            Details::FIRST_NAME => 'Jan',
            Details::LAST_NAME => 'Kowalski',
            Details::LANGUAGE => 'pl',
            Details::VALIDITY_TIME => 900,
            Details::RECURRING => 'STANDARD',
            Details::PAY_METHODS => ['payMethod' => ['type' => 'CARD_TOKEN', 'value' => 'TOK']],
            Details::PRODUCTS => [['name' => 'A', 'unitPrice' => 1000, 'quantity' => 1], ['name' => 'B', 'unitPrice' => 1100, 'quantity' => 1]],
        ]));

        $payload = (new PayloadFactory())->create($details, '300746', self::CONTINUE_URL, self::NOTIFY_URL);

        self::assertSame([['name' => 'A', 'unitPrice' => 1000, 'quantity' => 1], ['name' => 'B', 'unitPrice' => 1100, 'quantity' => 1]], $payload['products']);
        self::assertSame(
            ['email' => 'buyer@example.com', 'firstName' => 'Jan', 'lastName' => 'Kowalski', 'extCustomerId' => 'C-1', 'language' => 'pl'],
            $payload['buyer'],
        );
        self::assertSame(900, $payload['validityTime']);
        self::assertSame('STANDARD', $payload['recurring']);
        self::assertSame(['payMethod' => ['type' => 'CARD_TOKEN', 'value' => 'TOK']], $payload['payMethods']);
    }

    public function testOmitsTheBuyerWhenNothingIsKnownAboutThem(): void
    {
        $details = self::convertedDetails();
        unset($details[Details::CLIENT_EMAIL], $details[Details::CLIENT_ID]);

        $payload = (new PayloadFactory())->create(new Details(new \ArrayObject($details)), '300746', self::CONTINUE_URL, self::NOTIFY_URL);

        self::assertArrayNotHasKey('buyer', $payload);
    }

    #[DataProvider('requiredDetails')]
    public function testRequiresTheConvertedPaymentValues(string $key, string $hint): void
    {
        $details = self::convertedDetails();
        unset($details[$key]);

        self::assertThrows(
            MissingDetailException::class,
            static fn (): array => (new PayloadFactory())->create(new Details(new \ArrayObject($details)), '300746', self::CONTINUE_URL, self::NOTIFY_URL),
            sprintf('The payment detail "%s" is required: %s', $key, $hint),
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function requiredDetails(): iterable
    {
        yield 'description' => [Details::DESCRIPTION, 'convert the payment first'];
        yield 'total amount' => [Details::TOTAL_AMOUNT, 'convert the payment first'];
        yield 'currency' => [Details::CURRENCY_CODE, 'convert the payment first'];
        yield 'ext order id' => [Details::EXT_ORDER_ID, 'convert the payment first'];
        yield 'customer ip' => [Details::CUSTOMER_IP, 'set the buyer IP in the details or capture inside an HTTP request'];
    }

    /**
     * @return array<string, mixed>
     */
    private static function convertedDetails(): array
    {
        return [
            Details::TOTAL_AMOUNT => 2100,
            Details::CURRENCY_CODE => 'PLN',
            Details::EXT_ORDER_ID => 'INV-1',
            Details::DESCRIPTION => 'Insurance premium',
            Details::CLIENT_EMAIL => 'buyer@example.com',
            Details::CLIENT_ID => 'C-1',
            Details::CUSTOMER_IP => '10.0.0.7',
        ];
    }
}
