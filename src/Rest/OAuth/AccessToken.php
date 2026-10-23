<?php

declare(strict_types=1);

namespace PayumPayU\Rest\OAuth;

use PayumPayU\Json\Document;

final readonly class AccessToken
{
    public function __construct(
        public string $value,
        public \DateTimeImmutable $expiresAt,
    ) {
    }

    public static function fromDocument(Document $document, \DateTimeImmutable $issuedAt): self
    {
        return new self(
            $document->string('access_token'),
            $issuedAt->modify(sprintf('+%d seconds', $document->int('expires_in'))),
        );
    }

    public function isUsableAt(\DateTimeImmutable $moment, int $leewaySeconds): bool
    {
        return $moment->modify(sprintf('+%d seconds', $leewaySeconds)) < $this->expiresAt;
    }
}
