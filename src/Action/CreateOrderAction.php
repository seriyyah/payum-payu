<?php

declare(strict_types=1);

namespace PayumPayU\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\Reply\HttpRedirect;
use Payum\Core\Request\GetHttpRequest;
use Payum\Core\Request\Sync;
use Payum\Core\Security\GenericTokenFactoryAwareInterface;
use Payum\Core\Security\TokenInterface;
use PayumPayU\Exception\LogicException;
use PayumPayU\Model\Details;
use PayumPayU\Model\Exception\MissingDetailException;
use PayumPayU\Model\OrderStatus;
use PayumPayU\Model\PayMethod;
use PayumPayU\Model\Recurring;
use PayumPayU\Order\PayloadFactory;
use PayumPayU\Recurring\CardTokenSelector;
use PayumPayU\Request\CreateOrder;

final class CreateOrderAction implements ActionInterface, ApiAwareInterface, GatewayAwareInterface, GenericTokenFactoryAwareInterface
{
    use ApiAwareTrait;
    use GatewayAwareTrait;
    use ModelRequestTrait;
    use TokenFactoryAwareTrait;

    public function __construct(
        private readonly PayloadFactory $payloadFactory,
        private readonly CardTokenSelector $cardTokenSelector,
    ) {}

    public function execute(mixed $request): void
    {
        $details = $this->detailsOf($request, CreateOrder::class);
        /** @var CreateOrder $request */
        $token = $request->getToken() ?? throw new LogicException(
            'CreateOrder needs a capture token for the continue and notify URLs; execute Capture with a token.',
        );

        $this->resolveCustomerIp($details);
        $this->resolveCardToken($details);

        $created = $this->api()->client->createOrder($this->payloadFactory->create(
            $details,
            $this->api()->configuration->posId,
            $token->getTargetUrl(),
            $this->notifyUrl($token),
        ));

        $details->setOrderId($created->orderId);
        $details->setStatus($details->status() ?? OrderStatus::New);

        if ($created->cardMaskedNumber !== null) {
            $details->setCardMaskedNumber($created->cardMaskedNumber);
        }

        if ($created->redirectUri !== null) {
            $details->setRedirectUri($created->redirectUri);

            throw new HttpRedirect($created->redirectUri);
        }

        $sync = new Sync($token);
        $sync->setModel($request->getFirstModel());
        $sync->setModel($request->getModel());
        $this->gateway()->execute($sync);
    }

    public function supports(mixed $request): bool
    {
        return $this->modelOf($request, CreateOrder::class) !== null;
    }

    private function resolveCustomerIp(Details $details): void
    {
        if ($details->customerIp() !== null) {
            return;
        }

        $httpRequest = new GetHttpRequest();
        $this->gateway()->execute($httpRequest);

        if ($httpRequest->clientIp === '') {
            return;
        }

        $details->setCustomerIp($httpRequest->clientIp);
    }

    /**
     * A STANDARD recurring charge without an explicit pay method is charged to
     * the card token PayU stores for the buyer.
     */
    private function resolveCardToken(Details $details): void
    {
        if ($details->recurring() !== Recurring::Standard || $details->payMethod() !== null) {
            return;
        }

        $payMethods = $this->api()->client->retrievePayMethods(
            $details->clientEmail()
                ?? throw MissingDetailException::forKey(Details::CLIENT_EMAIL, 'needed to look up the buyer card tokens'),
            $details->clientId() ?? throw MissingDetailException::forKey(Details::CLIENT_ID, 'needed to look up the buyer card tokens'),
        );
        $cardToken = $this->cardTokenSelector->select($payMethods, $details->cardMaskedNumber());

        $details->setPayMethod(PayMethod::cardToken($cardToken->value));
        $details->setCardMaskedNumber($cardToken->cardNumberMasked);
    }

    private function notifyUrl(TokenInterface $token): string
    {
        return $this->tokenFactory()->createNotifyToken($token->getGatewayName(), $token->getDetails())->getTargetUrl();
    }
}
