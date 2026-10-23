<?php

declare(strict_types=1);

namespace PayumPayU\Rest\OAuth;

use PayumPayU\Json\Document;
use PayumPayU\Json\Exception\DocumentException;
use PayumPayU\Rest\Configuration;
use PayumPayU\Rest\Exception\ApiException;
use PayumPayU\Rest\Exception\InvalidResponseException;
use PayumPayU\Rest\Transport;
use Psr\Clock\ClockInterface;

/**
 * Obtains bearer tokens and reuses them until shortly before they expire.
 */
final class TokenProvider implements TokenProviderInterface
{
    private const AUTHORIZE_PATH = '/pl/standard/user/oauth/authorize';

    private const EXPIRY_LEEWAY_SECONDS = 60;

    /**
     * @var array<string, AccessToken>
     */
    private array $tokens = [];

    public function __construct(
        private readonly Transport $transport,
        private readonly Configuration $configuration,
        private readonly ClockInterface $clock,
    ) {
    }

    public function token(Grant $grant): AccessToken
    {
        $cached = $this->tokens[$grant->cacheKey()] ?? null;

        if ($cached !== null && $cached->isUsableAt($this->clock->now(), self::EXPIRY_LEEWAY_SECONDS)) {
            return $cached;
        }

        return $this->tokens[$grant->cacheKey()] = $this->request($grant);
    }

    private function request(Grant $grant): AccessToken
    {
        $issuedAt = $this->clock->now();
        $response = $this->transport->send(
            'POST',
            $this->configuration->environment->baseUri() . self::AUTHORIZE_PATH,
            [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json',
            ],
            http_build_query($grant->toFormFields($this->configuration->oauthClientId, $this->configuration->oauthClientSecret)),
        );

        if ($response->getStatusCode() !== 200) {
            throw ApiException::fromHttpStatus('OAuth authorization', $response->getStatusCode());
        }

        try {
            return AccessToken::fromDocument(Document::fromJson((string) $response->getBody()), $issuedAt);
        } catch (DocumentException $exception) {
            throw InvalidResponseException::fromDocumentException($exception, 'OAuth authorization');
        }
    }
}
