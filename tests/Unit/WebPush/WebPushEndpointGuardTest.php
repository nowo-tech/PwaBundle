<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Unit\WebPush;

use Nowo\PwaBundle\WebPush\Exception\InvalidPushEndpointException;
use Nowo\PwaBundle\WebPush\WebPushEndpointGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WebPushEndpointGuardTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function allowedEndpoints(): iterable
    {
        yield 'fcm' => ['https://fcm.googleapis.com/fcm/send/abc'];
        yield 'mozilla' => ['https://updates.push.services.mozilla.com/wpush/v2/abc'];
        yield 'apple' => ['https://web.push.apple.com/QGx'];
        yield 'wns' => ['https://wns2-par02p.notify.windows.com/w/?token=abc'];
        yield 'uppercase + spaces' => ['  HTTPS://FCM.GOOGLEAPIS.COM/fcm/send/abc  '];
    }

    #[DataProvider('allowedEndpoints')]
    public function testAllowsPushServices(string $endpoint): void
    {
        (new WebPushEndpointGuard())->assertSafeEndpoint($endpoint);
        $this->addToAssertionCount(1);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function rejectedEndpoints(): iterable
    {
        yield 'garbage' => ['::not a url', 'Invalid push endpoint URL.'];
        yield 'no host' => ['https:///path', 'Invalid push endpoint URL.'];
        yield 'relative' => ['/push', 'Invalid push endpoint URL.'];
        yield 'http' => ['http://fcm.googleapis.com/fcm/send/abc', 'Push endpoint must use https.'];
        yield 'ipv4' => ['https://127.0.0.1/push', 'Push endpoint must not use a literal IP address.'];
        yield 'ipv6' => ['https://[::1]/push', 'Push endpoint must not use a literal IP address.'];
        yield 'other host' => ['https://evil.example.com/push', 'Push endpoint host is not an allowed push service.'];
        yield 'suffix trick' => ['https://evilfcm.googleapis.com.example.com/push', 'Push endpoint host is not an allowed push service.'];
    }

    #[DataProvider('rejectedEndpoints')]
    public function testRejectsUnsafeEndpoints(string $endpoint, string $message): void
    {
        $this->expectException(InvalidPushEndpointException::class);
        $this->expectExceptionMessage($message);

        (new WebPushEndpointGuard())->assertSafeEndpoint($endpoint);
    }

    public function testCustomSuffixesAreNormalised(): void
    {
        $guard = new WebPushEndpointGuard(['', ' .Push.Example.org ']);
        $guard->assertSafeEndpoint('https://eu.push.example.org/x');
        $guard->assertSafeEndpoint('https://push.example.org/x');

        $this->expectException(InvalidPushEndpointException::class);
        $guard->assertSafeEndpoint('https://fcm.googleapis.com/fcm/send/abc');
    }
}
