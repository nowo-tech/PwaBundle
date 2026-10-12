<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Unit\WebPush;

use Http\Discovery\ClassDiscovery;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush;
use Nowo\PwaBundle\WebPush\Exception\InvalidPushEndpointException;
use Nowo\PwaBundle\WebPush\Exception\WebPushDependencyException;
use Nowo\PwaBundle\WebPush\MinishlinkWebPushSender;
use Nowo\PwaBundle\WebPush\PushSubscriptionData;
use Nowo\PwaBundle\WebPush\WebPushRequirements;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\NullLogger;
use RuntimeException;

use function base64_encode;
use function openssl_pkey_get_details;
use function openssl_pkey_new;
use function random_bytes;
use function rtrim;
use function str_pad;
use function strtr;

use const OPENSSL_KEYTYPE_EC;
use const STR_PAD_LEFT;

final class MinishlinkWebPushSenderTest extends TestCase
{
    /** @var array{publicKey: string, privateKey: string} */
    private static array $vapid;

    public static function setUpBeforeClass(): void
    {
        self::$vapid = VAPID::createVapidKeys();
    }

    public function testConfigurationFlags(): void
    {
        $sender = new MinishlinkWebPushSender(self::$vapid['publicKey'], self::$vapid['privateKey']);
        self::assertTrue($sender->isConfigured());
        self::assertSame(self::$vapid['publicKey'], $sender->getPublicKey());

        $unconfigured = new MinishlinkWebPushSender(self::$vapid['publicKey'], '');
        self::assertFalse($unconfigured->isConfigured());
        self::assertNull($unconfigured->getPublicKey());
    }

    public function testRefusesToSendWithoutKeys(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('VAPID keys are not configured');

        (new MinishlinkWebPushSender('', ''))->send($this->subscription(), '{}');
    }

    public function testRejectsUnsafeEndpointBeforeAnyRequest(): void
    {
        $client = $this->client(new Response(201));

        try {
            $this->sender($client)->send($this->subscription('https://evil.example.com/push'), '{}');
            self::fail('Expected exception');
        } catch (InvalidPushEndpointException) {
            self::assertSame([], $client->requests);
        }
    }

    public function testSendsEncryptedNotification(): void
    {
        $client = $this->client(new Response(201));

        $report = $this->sender($client, 'mailto:ops@example.com')->send($this->subscription(), '{"title":"Hi"}', ['TTL' => 60, 'urgency' => 'high']);

        self::assertTrue($report->success);
        self::assertSame(201, $report->statusCode);
        self::assertNull($report->reason);
        self::assertFalse($report->expired);
        self::assertCount(1, $client->requests);
        $request = $client->requests[0];
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://fcm.googleapis.com/fcm/send/abc', (string) $request->getUri());
        self::assertSame('60', $request->getHeaderLine('TTL'));
        self::assertSame('high', $request->getHeaderLine('Urgency'));
        self::assertStringStartsWith('vapid t=', $request->getHeaderLine('Authorization'));
    }

    public function testReportsExpiredAndRejectedSubscriptions(): void
    {
        $expired = $this->sender($this->client(new Response(410, [], 'Gone')))->send($this->subscription(), '{}');
        self::assertFalse($expired->success);
        self::assertTrue($expired->expired);
        self::assertSame(410, $expired->statusCode);
        self::assertNotNull($expired->reason);

        $rejected = $this->sender($this->client(new Response(403, [], 'Forbidden')))->send($this->subscription(), '{}');
        self::assertFalse($rejected->success);
        self::assertFalse($rejected->expired);
        self::assertSame(403, $rejected->statusCode);
    }

    public function testReportsTransportErrors(): void
    {
        $client = new class implements ClientInterface {
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                throw new class('timeout') extends RuntimeException implements ClientExceptionInterface {};
            }
        };

        $report = $this->sender($client)->send($this->subscription(), '{}');
        self::assertFalse($report->success);
        self::assertNull($report->statusCode);
        self::assertSame('timeout', $report->reason);
    }

    public function testMissingLibraryIsReportedAsDependencyError(): void
    {
        $sender = new MinishlinkWebPushSender(
            self::$vapid['publicKey'],
            self::$vapid['privateKey'],
            requirements: new WebPushRequirements(classExists: static fn (string $class): bool => $class !== WebPush::class),
        );

        $this->expectException(WebPushDependencyException::class);
        $sender->send($this->subscription(), '{}');
    }

    public function testMissingPsrImplementationIsReportedAsDependencyError(): void
    {
        $strategies = ClassDiscovery::getStrategies();
        ClassDiscovery::setStrategies([]);

        try {
            (new MinishlinkWebPushSender(self::$vapid['publicKey'], self::$vapid['privateKey'], logger: new NullLogger()))->send($this->subscription(), '{}');
            self::fail('Expected exception');
        } catch (WebPushDependencyException $e) {
            self::assertSame(WebPushRequirements::missingHttpImplementationMessage(), $e->getMessage());
            self::assertNotNull($e->getPrevious());
        } finally {
            ClassDiscovery::setStrategies($strategies);
        }
    }

    private function sender(ClientInterface $client, string $subject = ''): MinishlinkWebPushSender
    {
        $factory = new Psr17Factory();

        return new MinishlinkWebPushSender(
            self::$vapid['publicKey'],
            self::$vapid['privateKey'],
            $subject,
            httpClient: $client,
            requestFactory: $factory,
            streamFactory: $factory,
            logger: new NullLogger(),
        );
    }

    /**
     * @return ClientInterface&object{requests: list<RequestInterface>}
     */
    private function client(ResponseInterface $response): ClientInterface
    {
        return new class($response) implements ClientInterface {
            /** @var list<RequestInterface> */
            public array $requests = [];

            public function __construct(private readonly ResponseInterface $response)
            {
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->requests[] = $request;

                return $this->response;
            }
        };
    }

    private function subscription(string $endpoint = 'https://fcm.googleapis.com/fcm/send/abc'): PushSubscriptionData
    {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        self::assertNotFalse($key);
        $details = openssl_pkey_get_details($key);
        self::assertIsArray($details);
        $public = "\x04" . str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT);

        return new PushSubscriptionData($endpoint, $this->base64Url($public), $this->base64Url(random_bytes(16)));
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
