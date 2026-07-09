<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\DataCollector;

use Nowo\PwaBundle\Service\PwaRouteTargeting;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Throwable;

use function is_array;
use function is_string;

/**
 * Exposes PWA configuration and per-request activation in the Web Profiler.
 */
final class PwaDataCollector extends DataCollector
{
    /**
     * @param array<string, mixed> $manifestConfig
     * @param array<string, mixed> $serviceWorkerConfig
     * @param array<string, mixed> $installPromptConfig
     * @param array<string, mixed> $installLinksConfig
     * @param array<string, mixed> $clientConfig
     * @param array{match_by?: string, mode: string, routes: list<string>} $routeTargetingConfig
     * @param array<string, array{path: string, name: string}> $routes
     */
    public function __construct(
        private readonly bool $enabled,
        private readonly array $manifestConfig,
        private readonly array $serviceWorkerConfig,
        private readonly array $installPromptConfig,
        private readonly array $installLinksConfig,
        private readonly array $clientConfig,
        private readonly array $routeTargetingConfig,
        private readonly array $routes,
        private readonly PwaRouteTargeting $routeTargeting,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function collect(Request $request, Response $response, ?Throwable $exception = null): void
    {
        $route        = $request->attributes->get('_route');
        $currentRoute = is_string($route) ? $route : '';

        $activeOnRoute = $this->enabled && $this->routeTargeting->shouldApply(
            $currentRoute,
            $request->getPathInfo(),
            $this->routeTargetingConfig['mode'],
            $this->routeTargetingConfig['routes'],
            $this->routeTargetingConfig['match_by'] ?? PwaRouteTargeting::MATCH_BY_NAME,
        );

        $this->data = [
            'enabled'                      => $this->enabled,
            'active_on_route'              => $activeOnRoute,
            'current_route'                => $currentRoute,
            'route_targeting_mode'         => (string) $this->routeTargetingConfig['mode'],
            'route_targeting_routes'       => $this->routeTargetingConfig['routes'],
            'manifest_name'                => (string) ($this->manifestConfig['name'] ?? ''),
            'manifest_short_name'          => (string) ($this->manifestConfig['short_name'] ?? ''),
            'manifest_display'             => (string) ($this->manifestConfig['display'] ?? 'standalone'),
            'manifest_lang'                => (string) ($this->manifestConfig['lang'] ?? 'en'),
            'manifest_start_url'           => (string) ($this->manifestConfig['start_url'] ?? '/'),
            'manifest_scope'               => (string) ($this->manifestConfig['scope'] ?? '/'),
            'service_worker_enabled'       => (bool) ($this->serviceWorkerConfig['enabled'] ?? true),
            'service_worker_strategy'      => (string) ($this->serviceWorkerConfig['strategy'] ?? 'network-first'),
            'service_worker_cache_version' => (string) ($this->serviceWorkerConfig['cache_version'] ?? 'v1'),
            'install_prompt_enabled'       => (bool) ($this->installPromptConfig['enabled'] ?? true),
            'install_links_enabled'        => (bool) ($this->installLinksConfig['enabled'] ?? true),
            'client_register_on_load'      => (bool) ($this->clientConfig['register_on_load'] ?? true),
            'manifest_url'                 => $this->urlGenerator->generate($this->routes['manifest']['name']),
            'service_worker_url'           => $this->urlGenerator->generate($this->routes['service_worker']['name']),
            'offline_url'                  => $this->urlGenerator->generate($this->routes['offline']['name']),
        ];
    }

    public function reset(): void
    {
        $this->data = [];
    }

    public function getName(): string
    {
        return 'nowo_pwa';
    }

    public function isEnabled(): bool
    {
        return (bool) ($this->data['enabled'] ?? false);
    }

    public function isActiveOnRoute(): bool
    {
        return (bool) ($this->data['active_on_route'] ?? false);
    }

    public function getCurrentRoute(): string
    {
        return (string) ($this->data['current_route'] ?? '');
    }

    public function getRouteTargetingMode(): string
    {
        return (string) ($this->data['route_targeting_mode'] ?? 'all');
    }

    /**
     * @return list<string>
     */
    public function getRouteTargetingRoutes(): array
    {
        $routes = $this->data['route_targeting_routes'] ?? [];

        return is_array($routes) ? array_values(array_filter($routes, is_string(...))) : [];
    }

    public function getManifestName(): string
    {
        return (string) ($this->data['manifest_name'] ?? '');
    }

    public function getManifestShortName(): string
    {
        return (string) ($this->data['manifest_short_name'] ?? '');
    }

    public function getManifestDisplay(): string
    {
        return (string) ($this->data['manifest_display'] ?? 'standalone');
    }

    public function getManifestLang(): string
    {
        return (string) ($this->data['manifest_lang'] ?? 'en');
    }

    public function getManifestStartUrl(): string
    {
        return (string) ($this->data['manifest_start_url'] ?? '/');
    }

    public function getManifestScope(): string
    {
        return (string) ($this->data['manifest_scope'] ?? '/');
    }

    public function isServiceWorkerEnabled(): bool
    {
        return (bool) ($this->data['service_worker_enabled'] ?? false);
    }

    public function getServiceWorkerStrategy(): string
    {
        return (string) ($this->data['service_worker_strategy'] ?? 'network-first');
    }

    public function getServiceWorkerCacheVersion(): string
    {
        return (string) ($this->data['service_worker_cache_version'] ?? 'v1');
    }

    public function isInstallPromptEnabled(): bool
    {
        return (bool) ($this->data['install_prompt_enabled'] ?? false);
    }

    public function isInstallLinksEnabled(): bool
    {
        return (bool) ($this->data['install_links_enabled'] ?? false);
    }

    public function isClientRegisterOnLoad(): bool
    {
        return (bool) ($this->data['client_register_on_load'] ?? false);
    }

    public function getManifestUrl(): string
    {
        return (string) ($this->data['manifest_url'] ?? '');
    }

    public function getServiceWorkerUrl(): string
    {
        return (string) ($this->data['service_worker_url'] ?? '');
    }

    public function getOfflineUrl(): string
    {
        return (string) ($this->data['offline_url'] ?? '');
    }
}
