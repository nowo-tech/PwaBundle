<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Unit\Twig;

use Nowo\PwaBundle\Service\PwaRouteTargeting;
use Nowo\PwaBundle\Twig\PwaTwigExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFunction;

final class PwaTwigExtensionTest extends TestCase
{
    /** @return array<string, mixed> */
    private function baseConfig(bool $enabled = true, bool $installPrompt = true): array
    {
        return [
            'enabled'        => $enabled,
            'manifestConfig' => [
                'short_name'  => 'Demo',
                'theme_color' => '#112233',
                'icons'       => [['src' => '/icon.png', 'sizes' => '192x192', 'type' => 'image/png']],
            ],
            'metaConfig'           => ['msapplication_tile_color' => '#445566'],
            'serviceWorkerConfig'  => ['enabled' => true, 'scope' => '/'],
            'installPromptConfig'  => ['enabled' => $installPrompt],
            'installLinksConfig'   => ['enabled' => true],
            'clientConfig'         => ['register_on_load' => true, 'check_updates_on_visibility' => true, 'reload_on_update' => false],
            'routeTargetingConfig' => ['match_by' => 'name', 'mode' => 'all', 'routes' => []],
            'templates'            => [
                'head'           => 'head.twig',
                'install_prompt' => 'prompt.twig',
                'install_links'  => 'links.twig',
                'offline'        => 'offline.twig',
            ],
            'routes' => [
                'manifest'       => ['path' => '/manifest.webmanifest', 'name' => 'nowo_pwa_manifest'],
                'service_worker' => ['path' => '/sw.js', 'name' => 'nowo_pwa_service_worker'],
                'offline'        => ['path' => '/offline', 'name' => 'nowo_pwa_offline'],
            ],
        ];
    }

    private function createExtension(array $overrides = [], ?string $route = 'app_home'): PwaTwigExtension
    {
        $config = array_merge($this->baseConfig(), $overrides);

        $requestStack = new RequestStack();
        if ($route !== null) {
            $request = Request::create('/');
            $request->attributes->set('_route', $route);
            $requestStack->push($request);
        }

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static fn (string $name): string => match ($name) {
                'nowo_pwa_manifest'       => '/manifest.webmanifest',
                'nowo_pwa_service_worker' => '/sw.js',
                default                   => '/offline',
            },
        );

        return new PwaTwigExtension(
            $config['enabled'],
            new Environment(new ArrayLoader([
                'head.twig'   => '<link rel="manifest" href="{{ manifest_url }}">',
                'prompt.twig' => '<div id="install">Install</div>',
                'links.twig'  => '<div id="nowo-pwa-install-links">Links</div>',
            ])),
            $requestStack,
            $urlGenerator,
            new PwaRouteTargeting(),
            $config['manifestConfig'],
            $config['metaConfig'],
            $config['serviceWorkerConfig'],
            $config['installPromptConfig'],
            $config['installLinksConfig'],
            $config['clientConfig'],
            $config['routeTargetingConfig'],
            $config['templates'],
            $config['routes'],
        );
    }

    public function testRenderHeadAndInstallPrompt(): void
    {
        $extension = $this->createExtension();
        self::assertTrue($extension->isEnabledOnCurrentRoute());
        self::assertStringContainsString('manifest', $extension->renderHead());
        self::assertStringContainsString('Install', $extension->renderInstallPrompt());
        self::assertStringContainsString('Links', $extension->renderInstallLinks());
    }

    public function testDisabledBundleReturnsEmpty(): void
    {
        $extension = $this->createExtension(['enabled' => false]);
        self::assertFalse($extension->isEnabledOnCurrentRoute());
        self::assertSame('', $extension->renderHead());
        self::assertSame('', $extension->renderInstallPrompt());
    }

    public function testInstallPromptDisabled(): void
    {
        $extension = $this->createExtension(['installPromptConfig' => ['enabled' => false]]);
        self::assertSame('', $extension->renderInstallPrompt());
    }

    public function testInstallLinksDisabled(): void
    {
        $extension = $this->createExtension(['installLinksConfig' => ['enabled' => false]]);
        self::assertSame('', $extension->renderInstallLinks());
    }

    public function testRouteTargetingExceptMode(): void
    {
        $extension = $this->createExtension([
            'routeTargetingConfig' => ['mode' => 'except', 'routes' => ['app_home']],
        ]);
        self::assertFalse($extension->isEnabledOnCurrentRoute());
        self::assertSame('', $extension->renderHead('app_home'));
    }

    public function testOnlyModeRequiresMatchingRoute(): void
    {
        $extension = $this->createExtension([
            'routeTargetingConfig' => ['mode' => 'only', 'routes' => ['app_home']],
        ]);
        self::assertTrue($extension->isEnabledOnCurrentRoute());
        self::assertFalse($extension->isEnabledOnCurrentRoute('other'));
    }

    public function testNoRequestUsesEmptyRoute(): void
    {
        $extension = $this->createExtension([
            'routeTargetingConfig' => ['mode' => 'only', 'routes' => ['app_home']],
        ], route: null);
        self::assertFalse($extension->isEnabledOnCurrentRoute());
    }

    public function testExposesTwigFunctions(): void
    {
        $extension = $this->createExtension();
        $names     = array_map(static fn (TwigFunction $fn): string => $fn->getName(), $extension->getFunctions());
        self::assertSame(['nowo_pwa_enabled', 'nowo_pwa_head', 'nowo_pwa_install_prompt', 'nowo_pwa_install_links'], $names);
    }

    public function testInstallPromptUsesComponentRouteTargeting(): void
    {
        $extension = $this->createExtension([
            'installPromptConfig' => [
                'enabled'         => true,
                'route_targeting' => ['match_by' => 'name', 'mode' => 'only', 'routes' => ['app_home']],
            ],
        ]);
        self::assertStringContainsString('Install', $extension->renderInstallPrompt());
        self::assertSame('', $extension->renderInstallPrompt('other'));
    }

    public function testInstallLinksUsesComponentRouteTargetingByPath(): void
    {
        $extension = $this->createExtension([
            'installLinksConfig' => [
                'enabled'         => true,
                'route_targeting' => ['match_by' => 'path', 'mode' => 'only', 'routes' => ['/dashboard*']],
            ],
        ], route: 'any_route');

        $requestStack = new RequestStack();
        $request      = Request::create('/dashboard/stats');
        $request->attributes->set('_route', 'any_route');
        $requestStack->push($request);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/manifest.webmanifest');

        $config    = $this->baseConfig();
        $extension = new PwaTwigExtension(
            $config['enabled'],
            new Environment(new ArrayLoader(['links.twig' => '<div id="nowo-pwa-install-links">Links</div>'])),
            $requestStack,
            $urlGenerator,
            new PwaRouteTargeting(),
            $config['manifestConfig'],
            $config['metaConfig'],
            $config['serviceWorkerConfig'],
            $config['installPromptConfig'],
            [
                'enabled'         => true,
                'route_targeting' => ['match_by' => 'path', 'mode' => 'only', 'routes' => ['/dashboard*']],
            ],
            $config['clientConfig'],
            $config['routeTargetingConfig'],
            $config['templates'],
            $config['routes'],
        );

        self::assertStringContainsString('Links', $extension->renderInstallLinks());
        self::assertSame('', $extension->renderInstallLinks('any_route', '/other'));
    }
}
