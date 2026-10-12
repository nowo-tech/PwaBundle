<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\WebPush;

use Nowo\PwaBundle\WebPush\Exception\InvalidPushEndpointException;

use function filter_var;
use function is_array;
use function ltrim;
use function parse_url;
use function str_ends_with;
use function strtolower;
use function trim;

use const FILTER_VALIDATE_IP;

/**
 * Restricts Web Push endpoints to HTTPS push-service hosts (anti-SSRF): the server only
 * ever POSTs to browser vendors' push services, never to arbitrary URLs a client submitted.
 */
final readonly class WebPushEndpointGuard
{
    /**
     * Push services used by current browsers (FCM: Chrome/Edge/Brave/Opera/Samsung, Mozilla Autopush, Apple, Windows WNS).
     *
     * @var list<string>
     */
    public const DEFAULT_ALLOWED_HOST_SUFFIXES = [
        'fcm.googleapis.com',
        'android.googleapis.com',
        'push.services.mozilla.com',
        'push.apple.com',
        'notify.windows.com',
    ];

    /**
     * @param list<string> $allowedHostSuffixes
     */
    public function __construct(
        private array $allowedHostSuffixes = self::DEFAULT_ALLOWED_HOST_SUFFIXES,
    ) {
    }

    /**
     * @throws InvalidPushEndpointException when the endpoint is not an allowed push URL
     */
    public function assertSafeEndpoint(string $endpoint): void
    {
        $parts = parse_url(trim($endpoint));
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host']) || $parts['host'] === '') {
            throw new InvalidPushEndpointException('Invalid push endpoint URL.');
        }

        if (strtolower($parts['scheme']) !== 'https') {
            throw new InvalidPushEndpointException('Push endpoint must use https.');
        }

        $host = strtolower($parts['host']);
        if (filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) !== false) {
            throw new InvalidPushEndpointException('Push endpoint must not use a literal IP address.');
        }

        foreach ($this->allowedHostSuffixes as $suffix) {
            $suffix = strtolower(ltrim(trim($suffix), '.'));
            if ($suffix !== '' && ($host === $suffix || str_ends_with($host, '.' . $suffix))) {
                return;
            }
        }

        throw new InvalidPushEndpointException('Push endpoint host is not an allowed push service.');
    }
}
