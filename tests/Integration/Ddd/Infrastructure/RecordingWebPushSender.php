<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Integration\Ddd\Infrastructure;

use Nowo\PwaBundle\WebPush\PushSubscriptionData;
use Nowo\PwaBundle\WebPush\WebPushDeliveryReport;
use Nowo\PwaBundle\WebPush\WebPushSenderInterface;

use function str_contains;

/**
 * Test double of the sender: records the endpoints, answers 410 Gone for endpoints containing "gone".
 * The test drains {@see take()} after every request.
 */
final class RecordingWebPushSender implements WebPushSenderInterface
{
    /** @var list<string> */
    private array $endpoints = [];

    public function isConfigured(): bool
    {
        return true;
    }

    public function getPublicKey(): ?string
    {
        return null;
    }

    public function send(PushSubscriptionData $subscription, string $payload, array $options = []): WebPushDeliveryReport
    {
        $this->endpoints[] = $subscription->endpoint;

        return str_contains($subscription->endpoint, 'gone')
            ? new WebPushDeliveryReport(false, 410, 'Gone', true)
            : new WebPushDeliveryReport(true, 201);
    }

    /** @return list<string> the endpoints sent to since the last call */
    public function take(): array
    {
        $endpoints       = $this->endpoints;
        $this->endpoints = [];

        return $endpoints;
    }
}
