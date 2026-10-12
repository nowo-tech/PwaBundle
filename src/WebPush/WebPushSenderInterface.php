<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\WebPush;

use Nowo\PwaBundle\WebPush\Exception\InvalidPushEndpointException;
use Nowo\PwaBundle\WebPush\Exception\WebPushDependencyException;

/**
 * Sends one encrypted Web Push message to one subscription.
 */
interface WebPushSenderInterface
{
    /**
     * True when VAPID keys are configured (sending is possible).
     */
    public function isConfigured(): bool;

    /**
     * Public VAPID key (URL-safe base64) the browser must subscribe with, or null when not configured.
     */
    public function getPublicKey(): ?string;

    /**
     * @param array{TTL?: int, urgency?: string, topic?: string} $options
     *
     * @throws InvalidPushEndpointException when the endpoint is not an allowed push service URL
     * @throws WebPushDependencyException when minishlink/web-push or a PSR-17/PSR-18 implementation is missing
     */
    public function send(PushSubscriptionData $subscription, string $payload, array $options = []): WebPushDeliveryReport;
}
