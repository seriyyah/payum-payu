<?php

declare(strict_types=1);

namespace PayumPayU\Tests\Gateway;

use Nyholm\Psr7\Factory\Psr17Factory;
use Payum\Core\Extension\GenericTokenFactoryExtension;
use Payum\Core\GatewayInterface;
use Payum\Core\HttpClientInterface;
use Payum\Core\Model\Identity;
use Payum\Core\Model\Payment;
use Payum\Core\Model\Token;
use Payum\Core\Security\GenericTokenFactory;
use PayumPayU\Model\Details;
use PayumPayU\PayUGatewayFactory;
use PayumPayU\Tests\Double\ArrayHeaderProvider;
use PayumPayU\Tests\Double\FixedTokenFactory;
use PayumPayU\Tests\Double\FrozenClock;
use PayumPayU\Tests\Double\RecordingExtension;
use PayumPayU\Tests\Double\RecordingHttpClient;
use PayumPayU\Tests\TestCase;

/**
 * Builds a real Payum gateway from the factory with the HTTP boundary faked.
 */
abstract class GatewayTestCase extends TestCase
{
    protected const SECOND_KEY = 'b6ca15b0d1020e8094d9b5f8d163db54';
    protected const TARGET_URL = 'https://shop.example/payment/capture/capture-token';
    protected const AFTER_URL = 'https://shop.example/payment/done';
    protected const NOTIFY_URL = 'https://shop.example/payment/notify/notify-token';

    protected RecordingHttpClient $httpClient;

    protected FixedHttpRequestAction $httpRequest;

    protected RecordingExtension $recorder;

    /**
     * @var array<string, string>
     */
    protected array $headers = [];

    protected function setUp(): void
    {
        $this->httpClient = new RecordingHttpClient();
        $this->httpRequest = new FixedHttpRequestAction();
        $this->recorder = new RecordingExtension();
    }

    protected function gateway(): GatewayInterface
    {
        $notifyToken = new Token();
        $notifyToken->setTargetUrl(self::NOTIFY_URL);

        return (new PayUGatewayFactory())->create([
            PayUGatewayFactory::OPTION_ENVIRONMENT => 'sandbox',
            PayUGatewayFactory::OPTION_POS_ID => '300746',
            PayUGatewayFactory::OPTION_SIGNATURE_KEY => self::SECOND_KEY,
            PayUGatewayFactory::OPTION_OAUTH_CLIENT_ID => 'client-id',
            PayUGatewayFactory::OPTION_OAUTH_CLIENT_SECRET => 'client-secret',
            PayUGatewayFactory::CONFIG_HTTP_CLIENT => $this->httpClient,
            PayUGatewayFactory::CONFIG_REQUEST_FACTORY => new Psr17Factory(),
            PayUGatewayFactory::CONFIG_STREAM_FACTORY => new Psr17Factory(),
            PayUGatewayFactory::CONFIG_CLOCK => new FrozenClock(),
            PayUGatewayFactory::CONFIG_HEADER_PROVIDER => new ArrayHeaderProvider($this->headers),
            'payum.http_client' => self::createStub(HttpClientInterface::class),
            'httplug.client' => self::createStub(HttpClientInterface::class),
            'httplug.message_factory' => new Psr17Factory(),
            'httplug.stream_factory' => new Psr17Factory(),
            'payum.action.get_http_request' => $this->httpRequest,
            'payum.extension.recorder' => $this->recorder,
            'payum.extension.token_factory' => new GenericTokenFactoryExtension(
                new GenericTokenFactory(new FixedTokenFactory($notifyToken), ['notify' => 'payum_notify_do']),
            ),
        ]);
    }

    protected function captureToken(): Token
    {
        $token = new Token();
        $token->setGatewayName(PayUGatewayFactory::NAME);
        $token->setTargetUrl(self::TARGET_URL);
        $token->setAfterUrl(self::AFTER_URL);
        $token->setDetails(new Identity('1', Payment::class));

        return $token;
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return \ArrayObject<string, mixed>
     */
    protected static function convertedDetails(array $overrides = []): \ArrayObject
    {
        return new \ArrayObject($overrides + [
            Details::TOTAL_AMOUNT => 2100,
            Details::CURRENCY_CODE => 'PLN',
            Details::EXT_ORDER_ID => 'INV-1',
            Details::DESCRIPTION => 'Insurance premium',
            Details::CLIENT_EMAIL => 'buyer@example.com',
            Details::CLIENT_ID => 'C-1',
            Details::STATUS => 'NEW',
        ]);
    }

    protected function queueAccessToken(): void
    {
        $this->httpClient->willRespondJson(200, ['access_token' => 'tok', 'token_type' => 'bearer', 'expires_in' => 43199]);
    }

    protected static function sign(string $body, string $algorithm = 'SHA-256'): string
    {
        $hash = hash(strtolower(str_replace('-', '', $algorithm)), $body . self::SECOND_KEY);

        return sprintf('sender=checkout;signature=%s;algorithm=%s;content=DOCUMENT', $hash, $algorithm);
    }
}
