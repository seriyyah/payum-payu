<?php

declare(strict_types=1);

namespace PayumPayU\Tests\Rest;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Request;
use PayumPayU\Rest\Exception\TransportException;
use PayumPayU\Rest\Transport;
use PayumPayU\Tests\Double\NetworkException;
use PayumPayU\Tests\Double\RecordingHttpClient;
use PayumPayU\Tests\TestCase;
use Psr\Http\Message\ResponseInterface;

final class TransportTest extends TestCase
{
    public function testBuildsAndSendsTheRequest(): void
    {
        $httpClient = (new RecordingHttpClient())->willRespond(200, '{"ok":true}');
        $transport = new Transport($httpClient, new Psr17Factory(), new Psr17Factory());

        $response = $transport->send('POST', 'https://secure.snd.payu.com/api/v2_1/orders', ['Content-Type' => 'application/json'], '{"a":1}');

        self::assertSame(200, $response->getStatusCode());
        $request = $httpClient->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://secure.snd.payu.com/api/v2_1/orders', (string) $request->getUri());
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame('{"a":1}', (string) $request->getBody());
    }

    public function testBodylessRequestsCarryNoBody(): void
    {
        $httpClient = (new RecordingHttpClient())->willRespond(200);
        $transport = new Transport($httpClient, new Psr17Factory(), new Psr17Factory());

        $transport->send('GET', 'https://secure.payu.com/api/v2_1/orders/1', [], null);

        self::assertSame('', (string) $httpClient->lastRequest()->getBody());
    }

    public function testWrapsClientFailures(): void
    {
        $httpClient = (new RecordingHttpClient())->willThrow(new NetworkException(new Request('GET', 'https://secure.payu.com/x'), 'Connection refused'));
        $transport = new Transport($httpClient, new Psr17Factory(), new Psr17Factory());

        self::assertThrows(
            TransportException::class,
            static fn (): ResponseInterface => $transport->send('GET', 'https://secure.payu.com/api/v2_1/orders/1', [], null),
            'GET /api/v2_1/orders/1 failed: Connection refused',
        );
    }
}
