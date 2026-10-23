<?php

declare(strict_types=1);

namespace PayumPayU\Tests\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Exception\UnsupportedApiException;
use Payum\Core\GatewayInterface;
use Payum\Core\Model\Token;
use Payum\Core\Request\Cancel;
use Payum\Core\Request\Capture;
use Payum\Core\Request\Notify;
use Payum\Core\Request\Refund;
use Payum\Core\Request\Sync;
use PayumPayU\Action\CancelAction;
use PayumPayU\Action\CaptureAction;
use PayumPayU\Action\ConvertPaymentAction;
use PayumPayU\Action\CreateOrderAction;
use PayumPayU\Action\NotifyAction;
use PayumPayU\Action\RefundAction;
use PayumPayU\Action\RefundOrderAction;
use PayumPayU\Action\RetrievePayMethodsAction;
use PayumPayU\Action\StatusAction;
use PayumPayU\Action\SyncAction;
use PayumPayU\Api;
use PayumPayU\Exception\LogicException;
use PayumPayU\Model\Details;
use PayumPayU\Notification\NotificationParser;
use PayumPayU\Notification\SignatureVerifier;
use PayumPayU\Order\PayloadFactory;
use PayumPayU\Recurring\CardTokenSelector;
use PayumPayU\Request\CreateOrder;
use PayumPayU\Request\RefundOrder;
use PayumPayU\Rest\ClientInterface;
use PayumPayU\Rest\Configuration;
use PayumPayU\Rest\Environment;
use PayumPayU\Tests\Double\ArrayHeaderProvider;
use PayumPayU\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ActionContractTest extends TestCase
{
    #[DataProvider('actions')]
    public function testUnsupportedRequestsAreRejected(ActionInterface $action, object $unsupportedRequest): void
    {
        self::assertFalse($action->supports(new \stdClass()));
        self::assertFalse($action->supports($unsupportedRequest));
        self::assertThrows(RequestNotSupportedException::class, static fn () => $action->execute($unsupportedRequest));
    }

    /**
     * @return iterable<string, array{ActionInterface, object}>
     */
    public static function actions(): iterable
    {
        yield 'cancel' => [new CancelAction(), new Cancel(new \stdClass())];
        yield 'capture' => [new CaptureAction(), new Capture(new \stdClass())];
        yield 'convert' => [new ConvertPaymentAction(), new Cancel(new \ArrayObject())];
        yield 'create order' => [new CreateOrderAction(new PayloadFactory(), new CardTokenSelector()), new CreateOrder(new \stdClass())];
        yield 'notify' => [self::notifyAction(), new Notify(new \stdClass())];
        yield 'refund' => [new RefundAction(), new Refund(new \stdClass())];
        yield 'refund order' => [new RefundOrderAction(), new RefundOrder(new \stdClass())];
        yield 'retrieve pay methods' => [new RetrievePayMethodsAction(), new Sync(new \ArrayObject())];
        yield 'status' => [new StatusAction(), new Sync(new \ArrayObject())];
        yield 'sync' => [new SyncAction(), new Sync(new \stdClass())];
    }

    public function testOnlyThePackageApiIsAccepted(): void
    {
        $action = new SyncAction();

        self::assertThrows(UnsupportedApiException::class, static fn () => $action->setApi(new \stdClass()), 'It must be an instance of ' . Api::class);
    }

    public function testApiAwareActionsFailClearlyWithoutAnApi(): void
    {
        $action = new SyncAction();

        self::assertThrows(
            LogicException::class,
            static fn () => $action->execute(new Sync(new \ArrayObject([Details::ORDER_ID => 'O1']))),
            'The API has not been set on the action',
        );
    }

    public function testGatewayAwareActionsFailClearlyWithoutAGateway(): void
    {
        $action = new CaptureAction();

        self::assertThrows(
            LogicException::class,
            static fn () => $action->execute(new Capture(new \ArrayObject())),
            'The gateway has not been set on the action',
        );
    }

    public function testCreateOrderFailsClearlyWithoutATokenFactory(): void
    {
        $action = new CreateOrderAction(new PayloadFactory(), new CardTokenSelector());
        $action->setApi(new Api(
            new Configuration(Environment::Sandbox, 'pos', 'key', 'id', 'secret'),
            self::createStub(ClientInterface::class),
        ));
        $action->setGateway(self::createStub(GatewayInterface::class));
        $token = new Token();
        $token->setGatewayName('payu');
        $token->setTargetUrl('https://shop.example/continue');
        $request = new CreateOrder($token);
        $request->setModel(new \ArrayObject([Details::CUSTOMER_IP => '10.0.0.1']));

        self::assertThrows(LogicException::class, static fn () => $action->execute($request), 'The token factory has not been set on the action');
    }

    private static function notifyAction(): NotifyAction
    {
        return new NotifyAction(new ArrayHeaderProvider([]), new SignatureVerifier(), new NotificationParser());
    }
}
