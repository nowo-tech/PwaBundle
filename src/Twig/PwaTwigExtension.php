<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Twig;

use Nowo\PwaBundle\Service\PwaRouteTargeting;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

use function is_array;
use function is_string;

/**
 * Twig helpers to inject PWA head tags and install prompt.
 */
final class PwaTwigExtension extends AbstractExtension
{
    /**
     * @param array<string, mixed> $manifestConfig
     * @param array<string, mixed> $metaConfig
     * @param array<string, mixed> $serviceWorkerConfig
     * @param array<string, mixed> $installPromptConfig
     * @param array<string, mixed> $installLinksConfig
     * @param array<string, mixed> $clientConfig
     * @param array{match_by?: string, mode: string, routes: list<string>} $routeTargetingConfig
     * @param array{head: string, install_prompt: string, install_links: string, offline: string} $templates
     * @param array<string, array{path: string, name: string}> $routes
     */
    public function __construct(
        private readonly bool $enabled,
        private readonly Environment $twig,
        private readonly RequestStack $requestStack,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly PwaRouteTargeting $routeTargeting,
        private readonly array $manifestConfig,
        private readonly array $metaConfig,
        private readonly array $serviceWorkerConfig,
        private readonly array $installPromptConfig,
        private readonly array $installLinksConfig,
        private readonly array $clientConfig,
        private readonly array $routeTargetingConfig,
        private readonly array $templates,
        private readonly array $routes,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('nowo_pwa_enabled', $this->isEnabledOnCurrentRoute(...)),
            new TwigFunction('nowo_pwa_head', $this->renderHead(...), ['is_safe' => ['html']]),
            new TwigFunction('nowo_pwa_install_prompt', $this->renderInstallPrompt(...), ['is_safe' => ['html']]),
            new TwigFunction('nowo_pwa_install_links', $this->renderInstallLinks(...), ['is_safe' => ['html']]),
        ];
    }

    public function isEnabledOnCurrentRoute(?string $route = null, ?string $path = null): bool
    {
        if (!$this->enabled) {
            return false;
        }

        return $this->shouldApplyTargeting($this->routeTargetingConfig, $route, $path);
    }

    public function renderHead(?string $route = null, ?string $path = null): string
    {
        if (!$this->isEnabledOnCurrentRoute($route, $path)) {
            return '';
        }

        return $this->twig->render($this->templates['head'], $this->buildViewContext());
    }

    public function renderInstallPrompt(?string $route = null, ?string $path = null): string
    {
        if (!$this->enabled || !($this->installPromptConfig['enabled'] ?? true)) {
            return '';
        }

        if (!$this->shouldApplyComponentTargeting($this->installPromptConfig, $route, $path)) {
            return '';
        }

        return $this->twig->render($this->templates['install_prompt'], $this->buildViewContext());
    }

    public function renderInstallLinks(?string $route = null, ?string $path = null): string
    {
        if (!$this->enabled || !($this->installLinksConfig['enabled'] ?? true)) {
            return '';
        }

        if (!$this->shouldApplyComponentTargeting($this->installLinksConfig, $route, $path)) {
            return '';
        }

        return $this->twig->render($this->templates['install_links'], $this->buildViewContext());
    }

    /**
     * @param array<string, mixed> $config
     */
    private function shouldApplyComponentTargeting(array $config, ?string $route, ?string $path): bool
    {
        $targeting = $config['route_targeting'] ?? null;

        if (!is_array($targeting)) {
            return true;
        }

        return $this->shouldApplyTargeting($targeting, $route, $path);
    }

    /**
     * @param array{match_by?: string, mode?: string, routes?: list<string>} $targeting
     */
    private function shouldApplyTargeting(array $targeting, ?string $route, ?string $path): bool
    {
        return $this->routeTargeting->shouldApply(
            $route ?? $this->resolveCurrentRoute(),
            $path ?? $this->resolveCurrentPath(),
            (string) ($targeting['mode'] ?? PwaRouteTargeting::MODE_ALL),
            (array) ($targeting['routes'] ?? []),
            (string) ($targeting['match_by'] ?? PwaRouteTargeting::MATCH_BY_NAME),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildViewContext(): array
    {
        return [
            'manifest'           => $this->manifestConfig,
            'meta'               => $this->metaConfig,
            'service_worker'     => $this->serviceWorkerConfig,
            'install_prompt'     => $this->installPromptConfig,
            'install_links'      => $this->installLinksConfig,
            'client'             => $this->clientConfig,
            'manifest_url'       => $this->urlGenerator->generate($this->routes['manifest']['name']),
            'service_worker_url' => $this->urlGenerator->generate($this->routes['service_worker']['name']),
            'theme_color'        => (string) ($this->manifestConfig['theme_color'] ?? '#0f172a'),
        ];
    }

    private function resolveCurrentRoute(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            return '';
        }

        $route = $request->attributes->get('_route');

        return is_string($route) ? $route : '';
    }

    private function resolveCurrentPath(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            return '';
        }

        return $request->getPathInfo();
    }
}
