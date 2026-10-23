<?php

declare(strict_types=1);

namespace PayumPayU\Rest\Response;

use PayumPayU\Json\Document;

final readonly class PayMethods
{
    /**
     * @param list<CardToken> $cardTokens
     */
    public function __construct(
        public array $cardTokens,
    ) {
    }

    public static function fromDocument(Document $document): self
    {
        $cardTokens = $document->has('cardTokens') ? $document->documents('cardTokens') : [];

        return new self(array_map(CardToken::fromDocument(...), $cardTokens));
    }

    /**
     * @return list<CardToken>
     */
    public function activeCardTokens(): array
    {
        return array_values(array_filter($this->cardTokens, static fn (CardToken $token): bool => $token->isActive()));
    }
}
