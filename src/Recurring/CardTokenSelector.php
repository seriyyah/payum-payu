<?php

declare(strict_types=1);

namespace PayumPayU\Recurring;

use PayumPayU\Recurring\Exception\CardTokenNotFoundException;
use PayumPayU\Rest\Response\CardToken;
use PayumPayU\Rest\Response\PayMethods;

/**
 * Chooses the card to charge for a STANDARD recurring payment: the buyer's
 * preferred active card, or the first active one, optionally restricted to
 * a masked card number.
 */
final class CardTokenSelector
{
    /**
     * @throws CardTokenNotFoundException
     */
    public function select(PayMethods $payMethods, ?string $cardNumberMasked): CardToken
    {
        $candidates = array_values(array_filter(
            $payMethods->activeCardTokens(),
            static fn (CardToken $token): bool => $cardNumberMasked === null || $token->cardNumberMasked === $cardNumberMasked,
        ));

        foreach ($candidates as $candidate) {
            if ($candidate->preferred) {
                return $candidate;
            }
        }

        return $candidates[0] ?? throw CardTokenNotFoundException::forBuyer($cardNumberMasked);
    }
}
