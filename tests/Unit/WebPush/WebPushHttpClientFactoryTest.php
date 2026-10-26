<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Unit\WebPush;

use InvalidArgumentException;
use Nowo\PwaBundle\WebPush\WebPushHttpClientFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\HttpClient\Response\MockResponse;

use function fclose;
use function microtime;
use function stream_socket_get_name;
use function stream_socket_server;

final class WebPushHttpClientFactoryTest extends TestCase
{
    public function testDefaultsAreSane(): void
    {
        $factory = new WebPushHttpClientFactory();

        self::assertSame(5.0, $factory->timeout);
        self::assertSame(3.0, $factory->connectTimeout);
        self::assertSame(['timeout' => 3.0, 'max_duration' => 5.0], $factory->options());
    }

    public function testRejectsNonPositiveTimeouts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('greater than 0');

        new WebPushHttpClientFactory(0.0, 0.0);
    }

    public function testRejectsConnectTimeoutAboveTotalTimeout(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must not exceed the total timeout');

        new WebPushHttpClientFactory(1.0, 2.0);
    }

    public function testHostSymfonyClientIsKeptWithBundleDeadlines(): void
    {
        $seen = [];
        $host = new Psr18Client(new MockHttpClient(static function (string $method, string $url, array $options) use (&$seen): MockResponse {
            $seen = $options;

            return new MockResponse('', ['http_code' => 201]);
        }), new Psr17Factory(), new Psr17Factory());

        $client = (new WebPushHttpClientFactory(8.0, 2.0))->create($host);

        self::assertInstanceOf(Psr18Client::class, $client);
        self::assertNotSame($host, $client);
        self::assertSame(201, $client->sendRequest((new Psr17Factory())->createRequest('POST', 'https://fcm.googleapis.com/fcm/send/abc'))->getStatusCode());
        self::assertSame(2.0, $seen['timeout']);
        self::assertSame(8.0, $seen['max_duration']);
    }

    public function testForeignHostClientIsUsedAsIs(): void
    {
        $host = new class implements ClientInterface {
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                return new Response(201);
            }
        };

        self::assertSame($host, (new WebPushHttpClientFactory())->create($host));
    }

    public function testBuildsSymfonyClientWhenTheHostHasNone(): void
    {
        $streams = new Psr17Factory();

        self::assertInstanceOf(Psr18Client::class, (new WebPushHttpClientFactory())->create());
        self::assertInstanceOf(Psr18Client::class, (new WebPushHttpClientFactory())->create(null, $streams));
    }

    public function testFallsBackToDiscoveryWithoutSymfonyHttpClient(): void
    {
        $factory = new WebPushHttpClientFactory(classExists: static fn (string $class): bool => $class !== Psr18Client::class);

        self::assertNull($factory->create());
    }

    /**
     * REQ-RUNTIME-001 §4: an unresponsive push service (TCP accepted, no HTTP response ever sent) must not pin
     * the PHP thread: the bundle-built client gives up once the configured deadline elapses.
     */
    public function testUnresponsiveServerHitsTheDeadline(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($server === false) {
            self::markTestSkipped('Cannot open a local TCP socket: ' . $errstr);
        }
        $address = stream_socket_get_name($server, false);
        self::assertIsString($address);

        $client = (new WebPushHttpClientFactory(1.0, 0.5))->create();
        self::assertNotNull($client);

        $start = microtime(true);
        try {
            $client->sendRequest((new Psr17Factory())->createRequest('POST', 'http://' . $address . '/push'));
            self::fail('Expected a network timeout');
        } catch (NetworkExceptionInterface $e) {
            self::assertStringContainsStringIgnoringCase('timeout', $e->getMessage());
        } finally {
            fclose($server);
        }

        self::assertLessThan(3.0, microtime(true) - $start, 'The bundle deadline must fire long before default_socket_timeout (60 s).');
    }
}
