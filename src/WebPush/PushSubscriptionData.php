<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\WebPush;

use function is_string;
use function parse_url;

use const PHP_URL_HOST;

/**
 * Immutable browser push subscription (the JSON a browser returns from {@code PushSubscription.toJSON()}).
 *
 * Hosts map their own storage (Doctrine entity, cache, …) to this value object in
 * {@see PushSubscriptionProviderInterface}; the bundle never persists subscriptions itself.
 */
final readonly class PushSubscriptionData
{
    public function __construct(
        public string $endpoint,
        public string $publicKey,
        public string $authToken,
        public string $contentEncoding = 'aes128gcm',
    ) {
    }

    /**
     * Host of the push service endpoint (e.g. {@code fcm.googleapis.com}); empty when unparsable.
     */
    public function endpointHost(): string
    {
        $host = parse_url($this->endpoint, PHP_URL_HOST);

        return is_string($host) ? $host : '';
    }
}
