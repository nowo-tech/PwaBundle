<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Unit\WebPush;

use Nowo\PwaBundle\WebPush\Exception\PushTestDeniedException;
use Nowo\PwaBundle\WebPush\PushSubscriptionData;
use Nowo\PwaBundle\WebPush\WebPushDeliveryReport;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PushSubscriptionDataTest extends TestCase
{
    public function testDefaultsAndEndpointHost(): void
    {
        $subscription = new PushSubscriptionData('https://fcm.googleapis.com/fcm/send/abc', 'p256', 'auth');

        self::assertSame('aes128gcm', $subscription->contentEncoding);
        self::assertSame('fcm.googleapis.com', $subscription->endpointHost());
        self::assertSame('', (new PushSubscriptionData('not a url', 'p', 'a'))->endpointHost());
    }

    public function testDeliveryReportDefaults(): void
    {
        $report = new WebPushDeliveryReport(true);

        self::assertTrue($report->success);
        self::assertNull($report->statusCode);
        self::assertNull($report->reason);
        self::assertFalse($report->expired);
    }

    public function testDeniedExceptionCarriesCodeAndStatus(): void
    {
        $previous = new RuntimeException('inner');
        $e        = new PushTestDeniedException('preference_disabled', 409, 'Push is off', $previous);

        self::assertSame('preference_disabled', $e->errorCode);
        self::assertSame(409, $e->statusCode);
        self::assertSame('Push is off', $e->getMessage());
        self::assertSame($previous, $e->getPrevious());

        $default = new PushTestDeniedException('preference_disabled');
        self::assertSame(400, $default->statusCode);
        self::assertSame('preference_disabled', $default->getMessage());
    }
}
