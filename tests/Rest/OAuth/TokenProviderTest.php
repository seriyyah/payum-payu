<?php

declare(strict_types=1);

namespace PayumPayU\Tests\Rest\OAuth;

use Nyholm\Psr7\Factory\Psr17Factory;
use PayumPayU\Rest\Configuration;
use PayumPayU\Rest\Environment;
use PayumPayU\Rest\Exception\ApiException;
use PayumPayU\Rest\Exception\InvalidResponseException;
use PayumPayU\Rest\OAuth\AccessToken;
use PayumPayU\Rest\OAuth\Grant;
use PayumPayU\Rest\OAuth\TokenProvider;
use PayumPayU\Rest\Transport;
use PayumPayU\Tests\Double\FrozenClock;
use PayumPayU\Tests\Double\RecordingHttpClient;
use PayumPayU\Tests\TestCase;

final class TokenProviderTest extends TestCase
{
    private RecordingHttpClient $httpClient;

    private FrozenClock $clock;

    private TokenProvider $provider;

    public function testRequestsAMerchantTokenWithTheClientCredentialsGrant(): void
    {
        $this->httpClient->willRespondJson(200, [
            'access_token' => 'tok-1',
            'token_type' => 'bearer',
            'expires_in' => 43199,
            'grant_type' => 'client_credentials',
        ]);

        $token = $this->provider->token(Grant::clientCredentials());

        self::assertSame('tok-1', $token->value);
        $request = $this->httpClient->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://secure.snd.payu.com/pl/standard/user/oauth/authorize', (string) $request->getUri());
        self::assertSame('application/x-www-form-urlencoded', $request->getHeaderLine('Content-Type'));
        self::assertSame('grant_type=client_credentials&client_id=client-id&client_secret=client-secret', (string) $request->getBody());
    }

    public function testRequestsABuyerTokenWithTheTrustedMerchantGrant(): void
    {
        $this->httpClient->willRespondJson(200, ['access_token' => 'tok-2', 'expires_in' => 43199]);

        $this->provider->token(Grant::trustedMerchant('buyer@example.com', 'C-1'));

        self::assertSame(
            'grant_type=trusted_merchant&client_id=client-id&client_secret=client-secret&email=buyer%40example.com&ext_customer_id=C-1',
            (string) $this->httpClient->lastRequest()->getBody(),
        );
    }

    public function testReusesTokensPerGrantUntilTheyAreAboutToExpire(): void
    {
        $this->httpClient
            ->willRespondJson(200, ['access_token' => 'merchant', 'expires_in' => 3600])
            ->willRespondJson(200, ['access_token' => 'buyer', 'expires_in' => 3600])
            ->willRespondJson(200, ['access_token' => 'merchant-renewed', 'expires_in' => 3600]);

        self::assertSame('merchant', $this->provider->token(Grant::clientCredentials())->value);
        self::assertSame('buyer', $this->provider->token(Grant::trustedMerchant('b@example.com', 'C'))->value);
        self::assertSame('merchant', $this->provider->token(Grant::clientCredentials())->value);
        self::assertSame('buyer', $this->provider->token(Grant::trustedMerchant('b@example.com', 'C'))->value);

        $this->clock->advance('+59 minutes');

        self::assertSame('merchant-renewed', $this->provider->token(Grant::clientCredentials())->value);
        self::assertCount(3, $this->httpClient->requests());
    }

    public function testRejectedCredentialsSurfaceAsAnApiException(): void
    {
        $this->httpClient->willRespondJson(401, ['error' => 'invalid_client']);

        self::assertThrows(
            ApiException::class,
            fn (): AccessToken => $this->provider->token(Grant::clientCredentials()),
            'PayU rejected OAuth authorization with HTTP 401.',
        );
    }

    public function testUnreadableTokenResponseIsReported(): void
    {
        $this->httpClient->willRespondJson(200, ['token_type' => 'bearer']);

        self::assertThrows(
            InvalidResponseException::class,
            fn (): AccessToken => $this->provider->token(Grant::clientCredentials()),
            'Unexpected PayU response to OAuth authorization: The field "$.access_token" is missing.',
        );
    }

    protected function setUp(): void
    {
        $this->httpClient = new RecordingHttpClient();
        $this->clock = new FrozenClock();
        $this->provider = new TokenProvider(
            new Transport($this->httpClient, new Psr17Factory(), new Psr17Factory()),
            new Configuration(Environment::Sandbox, '300746', 'second-key', 'client-id', 'client-secret'),
            $this->clock,
        );
    }
}
