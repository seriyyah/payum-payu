<?php

declare(strict_types=1);

namespace PayumPayU\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\Reply\HttpResponse;
use Payum\Core\Request\GetHttpRequest;
use Payum\Core\Request\Notify;
use PayumPayU\Http\HeaderProviderInterface;
use PayumPayU\Model\Details;
use PayumPayU\Notification\Exception\InvalidSignatureException;
use PayumPayU\Notification\Exception\MalformedNotificationException;
use PayumPayU\Notification\NotificationParser;
use PayumPayU\Notification\OrderNotification;
use PayumPayU\Notification\RefundNotification;
use PayumPayU\Notification\SignatureVerifier;

/**
 * Handles PayU server-to-server notifications.
 *
 * Replies: 200 when applied or ignored as stale, 401 for a bad or missing
 * signature, 400 for an unreadable body or a foreign order, 412 when the
 * order id has not been stored yet so that PayU retries later.
 */
final class NotifyAction implements ActionInterface, ApiAwareInterface, GatewayAwareInterface
{
    use ApiAwareTrait;
    use GatewayAwareTrait;
    use ModelRequestTrait;

    public const SIGNATURE_HEADER = 'OpenPayu-Signature';

    public const LEGACY_SIGNATURE_HEADER = 'X-OpenPayU-Signature';

    public function __construct(
        private readonly HeaderProviderInterface $headerProvider,
        private readonly SignatureVerifier $signatureVerifier,
        private readonly NotificationParser $notificationParser,
    ) {}

    public function execute(mixed $request): void
    {
        $details = $this->detailsOf($request, Notify::class);
        $httpRequest = new GetHttpRequest();
        $this->gateway()->execute($httpRequest);

        $notification = $this->read($httpRequest->content);
        $orderId = $details->orderId() ?? throw new HttpResponse('The order is not stored yet, retry later.', 412);

        if ($notification->orderId !== $orderId) {
            throw new HttpResponse('The notification does not belong to this payment.', 400);
        }

        $this->apply($details, $notification);

        throw new HttpResponse('OK', 200);
    }

    public function supports(mixed $request): bool
    {
        return $this->modelOf($request, Notify::class) !== null;
    }

    private function read(string $body): OrderNotification|RefundNotification
    {
        $header = $this->headerProvider->header(self::SIGNATURE_HEADER) ?? $this->headerProvider->header(self::LEGACY_SIGNATURE_HEADER);

        try {
            $this->signatureVerifier->verify($body, $header, $this->api()->configuration->signatureKey);
        } catch (InvalidSignatureException $exception) {
            throw new HttpResponse($exception->getMessage(), 401);
        }

        try {
            return $this->notificationParser->parse($body);
        } catch (MalformedNotificationException $exception) {
            throw new HttpResponse($exception->getMessage(), 400);
        }
    }

    private function apply(Details $details, OrderNotification|RefundNotification $notification): void
    {
        if ($notification instanceof RefundNotification) {
            $details->setRefund($notification->refund);

            return;
        }

        if ($notification->status->supersedes($details->status())) {
            $details->setStatus($notification->status);
        }

        if ($notification->payMethodType === null) {
            return;
        }

        $details->setPayMethodType($notification->payMethodType);
    }
}
