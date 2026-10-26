<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\WebPush;

use Closure;
use Http\Discovery\Psr17FactoryDiscovery;
use InvalidArgumentException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;

use function class_exists;
use function sprintf;

/**
 * Resolves the PSR-18 client used for Web Push deliveries with bundle-level deadlines (REQ-RUNTIME-001).
 *
 * Precedence:
 *  1. A host {@see Psr18Client} (e.g. the FrameworkBundle `Psr\Http\Client\ClientInterface` service) is kept, but
 *     cloned with {@code withOptions()} so the bundle deadlines apply on top of the host defaults (proxy, headers…).
 *  2. Any other host PSR-18 client (Guzzle, Buzz…) is used as-is: its own timeouts apply and must be configured there.
 *  3. Without a host client, a new Symfony {@see Psr18Client} with the bundle deadlines is built when
 *     symfony/http-client is installed; otherwise null is returned and minishlink/web-push auto-discovers a client.
 *
 * Mapping to Symfony HttpClient options: {@code connectTimeout} → `timeout` (inactivity deadline, which bounds the
 * TCP/TLS connect phase and any stalled read) and {@code timeout} → `max_duration` (hard total deadline per request).
 */
final readonly class WebPushHttpClientFactory
{
    public const DEFAULT_TIMEOUT         = 5.0;
    public const DEFAULT_CONNECT_TIMEOUT = 3.0;

    /** @var Closure(string): bool */
    private Closure $classExists;

    /**
     * @param float $timeout total deadline per push request, in seconds
     * @param float $connectTimeout connect / inactivity deadline, in seconds (≤ timeout)
     * @param (Closure(string): bool)|null $classExists defaults to class_exists() (test seam for symfony/http-client)
     */
    public function __construct(
        public float $timeout = self::DEFAULT_TIMEOUT,
        public float $connectTimeout = self::DEFAULT_CONNECT_TIMEOUT,
        ?Closure $classExists = null,
    ) {
        $this->classExists = $classExists ?? class_exists(...);

        if ($timeout <= 0.0 || $connectTimeout <= 0.0) {
            throw new InvalidArgumentException('Web Push HTTP timeouts must be greater than 0 seconds.');
        }

        if ($connectTimeout > $timeout) {
            throw new InvalidArgumentException(sprintf('Web Push connect timeout (%s s) must not exceed the total timeout (%s s).', $connectTimeout, $timeout));
        }
    }

    /**
     * Symfony HttpClient options carrying the bundle deadlines.
     *
     * @return array{timeout: float, max_duration: float}
     */
    public function options(): array
    {
        return ['timeout' => $this->connectTimeout, 'max_duration' => $this->timeout];
    }

    /**
     * @param ClientInterface|null $hostClient PSR-18 client provided by the host container, if any
     * @param StreamFactoryInterface|null $streamFactory PSR-17 stream factory provided by the host container, if any
     *
     * @return ClientInterface|null null when no client can be built with deadlines (minishlink discovery takes over)
     */
    public function create(?ClientInterface $hostClient = null, ?StreamFactoryInterface $streamFactory = null): ?ClientInterface
    {
        if ($hostClient instanceof Psr18Client) {
            return $hostClient->withOptions($this->options());
        }

        if ($hostClient instanceof ClientInterface) {
            return $hostClient;
        }

        if (!($this->classExists)(Psr18Client::class)) {
            return null;
        }

        return new Psr18Client(
            HttpClient::create($this->options()),
            Psr17FactoryDiscovery::findResponseFactory(),
            $streamFactory ?? Psr17FactoryDiscovery::findStreamFactory(),
        );
    }
}
