<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Twig;

use Nowo\PwaBundle\Service\PwaRouteTargeting;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

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
     * @param array{mode: string, routes: list<string>} $routeTargetingConfig
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

    public function isEnabledOnCurrentRoute(?string $route = null): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $currentRoute = $route ?? $this->resolveCurrentRoute();

        return $this->routeTargeting->shouldApply(
            $currentRoute,
            (string) $this->routeTargetingConfig['mode'],
            $this->routeTargetingConfig['routes'],
        );
    }

    public function renderHead(?string $route = null): string
    {
        if (!$this->isEnabledOnCurrentRoute($route)) {
            return '';
        }

        return $this->twig->render($this->templates['head'], $this->buildViewContext());
    }

    public function renderInstallPrompt(?string $route = null): string
    {
        if (!$this->isEnabledOnCurrentRoute($route) || !($this->installPromptConfig['enabled'] ?? true)) {
            return '';
        }

        return $this->twig->render($this->templates['install_prompt'], $this->buildViewContext());
    }

    public function renderInstallLinks(?string $route = null): string
    {
        if (!$this->isEnabledOnCurrentRoute($route) || !($this->installLinksConfig['enabled'] ?? true)) {
            return '';
        }

        return $this->twig->render($this->templates['install_links'], $this->buildViewContext());
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
        if (!$request instanceof \Symfony\Component\HttpFoundation\Request) {
            return '';
        }

        $route = $request->attributes->get('_route');

        return is_string($route) ? $route : '';
    }
}
