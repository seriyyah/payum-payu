<?php

declare(strict_types=1);

namespace PayumPayU\Rest\OAuth;

/**
 * OAuth grant accepted by the PayU authorization endpoint.
 *
 * client_credentials authorizes merchant operations. trusted_merchant
 * authorizes operations on behalf of a buyer, such as listing their stored
 * card tokens.
 */
final readonly class Grant
{
    private const TYPE_CLIENT_CREDENTIALS = 'client_credentials';

    private const TYPE_TRUSTED_MERCHANT = 'trusted_merchant';

    /**
     * @param array{email: string, ext_customer_id: string}|null $buyer
     */
    private function __construct(
        private string $type,
        private ?array $buyer,
    ) {
    }

    public static function clientCredentials(): self
    {
        return new self(self::TYPE_CLIENT_CREDENTIALS, null);
    }

    public static function trustedMerchant(string $email, string $extCustomerId): self
    {
        return new self(self::TYPE_TRUSTED_MERCHANT, ['email' => $email, 'ext_customer_id' => $extCustomerId]);
    }

    public function cacheKey(): string
    {
        return implode(':', [$this->type, $this->buyer['email'] ?? '', $this->buyer['ext_customer_id'] ?? '']);
    }

    /**
     * @return array<string, string>
     */
    public function toFormFields(string $clientId, string $clientSecret): array
    {
        return [
            'grant_type' => $this->type,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ] + ($this->buyer ?? []);
    }
}
