<?php

declare(strict_types=1);

namespace PayumPayU\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Model\PaymentInterface;
use Payum\Core\Request\Convert;
use PayumPayU\Model\Details;
use PayumPayU\Model\OrderStatus;

/**
 * Maps a Payum payment onto the details array; keys set earlier by the
 * application (buyer name, recurring mode, pay method, products) are kept.
 */
final class ConvertPaymentAction implements ActionInterface
{
    private const TARGET = 'array';

    public function execute(mixed $request): void
    {
        if (! $request instanceof Convert) {
            throw RequestNotSupportedException::createActionNotSupported($this, $request);
        }

        $payment = $this->payment($request) ?? throw RequestNotSupportedException::createActionNotSupported($this, $request);
        $details = ArrayObject::ensureArrayObject($payment->getDetails());

        $details[Details::TOTAL_AMOUNT] = $payment->getTotalAmount();
        $details[Details::CURRENCY_CODE] = $payment->getCurrencyCode();
        $details[Details::EXT_ORDER_ID] = $payment->getNumber();
        $details[Details::DESCRIPTION] = $payment->getDescription();
        $details[Details::CLIENT_EMAIL] = $payment->getClientEmail();
        $details[Details::CLIENT_ID] = $payment->getClientId();
        $details[Details::STATUS] ??= OrderStatus::New->value;

        $request->setResult((array) $details);
    }

    public function supports(mixed $request): bool
    {
        return $request instanceof Convert && $this->payment($request) !== null;
    }

    private function payment(Convert $request): ?PaymentInterface
    {
        if ($request->getTo() !== self::TARGET) {
            return null;
        }

        $source = $request->getSource();

        return $source instanceof PaymentInterface ? $source : null;
    }
}
