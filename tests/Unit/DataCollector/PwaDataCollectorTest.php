<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Unit\DataCollector;

use Nowo\PwaBundle\DataCollector\PwaDataCollector;
use Nowo\PwaBundle\Service\PwaRouteTargeting;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PwaDataCollectorTest extends TestCase
{
    public function testCollectsActiveConfigurationOnMatchingRoute(): void
    {
        $collector = $this->createCollector();
        $request   = Request::create('/');
        $request->attributes->set('_route', 'app_home');

        $collector->collect($request, new Response());

        self::assertSame('nowo_pwa', $collector->getName());
        self::assertTrue($collector->isEnabled());
        self::assertTrue($collector->isActiveOnRoute());
        self::assertSame('app_home', $collector->getCurrentRoute());
        self::assertSame('all', $collector->getRouteTargetingMode());
        self::assertSame([], $collector->getRouteTargetingRoutes());
        self::assertSame('Demo App', $collector->getManifestName());
        self::assertSame('Demo', $collector->getManifestShortName());
        self::assertSame('standalone', $collector->getManifestDisplay());
        self::assertSame('en', $collector->getManifestLang());
        self::assertSame('/', $collector->getManifestStartUrl());
        self::assertSame('/', $collector->getManifestScope());
        self::assertTrue($collector->isServiceWorkerEnabled());
        self::assertSame('network-first', $collector->getServiceWorkerStrategy());
        self::assertSame('v1', $collector->getServiceWorkerCacheVersion());
        self::assertTrue($collector->isInstallPromptEnabled());
        self::assertTrue($collector->isInstallLinksEnabled());
        self::assertTrue($collector->isClientRegisterOnLoad());
        self::assertSame('/manifest.webmanifest', $collector->getManifestUrl());
        self::assertSame('/sw.js', $collector->getServiceWorkerUrl());
        self::assertSame('/offline', $collector->getOfflineUrl());
    }

    public function testCollectsInactiveWhenBundleDisabled(): void
    {
        $collector = $this->createCollector(enabled: false);
        $request   = Request::create('/');
        $request->attributes->set('_route', 'app_home');

        $collector->collect($request, new Response());

        self::assertFalse($collector->isEnabled());
        self::assertFalse($collector->isActiveOnRoute());
    }

    public function testCollectsInactiveWhenRouteIsExcluded(): void
    {
        $collector = $this->createCollector(routeTargetingConfig: [
            'mode'   => PwaRouteTargeting::MODE_EXCEPT,
            'routes' => ['admin_dashboard'],
        ]);
        $request = Request::create('/admin');
        $request->attributes->set('_route', 'admin_dashboard');

        $collector->collect($request, new Response());

        self::assertTrue($collector->isEnabled());
        self::assertFalse($collector->isActiveOnRoute());
        self::assertSame(PwaRouteTargeting::MODE_EXCEPT, $collector->getRouteTargetingMode());
        self::assertSame(['admin_dashboard'], $collector->getRouteTargetingRoutes());
    }

    public function testCollectsOnlyModeRequiresMatchingRoute(): void
    {
        $collector = $this->createCollector(routeTargetingConfig: [
            'mode'   => PwaRouteTargeting::MODE_ONLY,
            'routes' => ['app_home'],
        ]);

        $matchingRequest = Request::create('/');
        $matchingRequest->attributes->set('_route', 'app_home');
        $collector->collect($matchingRequest, new Response());
        self::assertTrue($collector->isActiveOnRoute());

        $otherRequest = Request::create('/other');
        $otherRequest->attributes->set('_route', 'other_route');
        $collector->collect($otherRequest, new Response());
        self::assertFalse($collector->isActiveOnRoute());
    }

    public function testResetClearsCollectedData(): void
    {
        $collector = $this->createCollector();
        $request   = Request::create('/');
        $request->attributes->set('_route', 'app_home');

        $collector->collect($request, new Response());
        $collector->reset();

        self::assertFalse($collector->isEnabled());
        self::assertSame('', $collector->getCurrentRoute());
        self::assertSame('', $collector->getManifestName());
    }

    public function testGettersReturnDefaultsBeforeCollect(): void
    {
        $collector = $this->createCollector();

        self::assertFalse($collector->isEnabled());
        self::assertFalse($collector->isActiveOnRoute());
        self::assertSame('', $collector->getCurrentRoute());
        self::assertSame('all', $collector->getRouteTargetingMode());
        self::assertSame([], $collector->getRouteTargetingRoutes());
        self::assertSame('', $collector->getManifestName());
        self::assertSame('standalone', $collector->getManifestDisplay());
        self::assertSame('en', $collector->getManifestLang());
        self::assertSame('/', $collector->getManifestStartUrl());
        self::assertSame('/', $collector->getManifestScope());
        self::assertFalse($collector->isServiceWorkerEnabled());
        self::assertSame('network-first', $collector->getServiceWorkerStrategy());
        self::assertSame('v1', $collector->getServiceWorkerCacheVersion());
        self::assertFalse($collector->isInstallPromptEnabled());
        self::assertFalse($collector->isInstallLinksEnabled());
        self::assertFalse($collector->isClientRegisterOnLoad());
        self::assertSame('', $collector->getManifestUrl());
        self::assertSame('', $collector->getServiceWorkerUrl());
        self::assertSame('', $collector->getOfflineUrl());
    }

    public function testCollectHandlesMissingRouteAttribute(): void
    {
        $collector = $this->createCollector();
        $collector->collect(Request::create('/'), new Response());

        self::assertSame('', $collector->getCurrentRoute());
        self::assertTrue($collector->isActiveOnRoute());
    }

    /**
     * @param array{mode: string, routes: list<string>} $routeTargetingConfig
     */
    private function createCollector(
        bool $enabled = true,
        array $routeTargetingConfig = ['mode' => 'all', 'routes' => []],
    ): PwaDataCollector {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnMap([
            ['nowo_pwa_manifest', [], UrlGeneratorInterface::ABSOLUTE_PATH, '/manifest.webmanifest'],
            ['nowo_pwa_service_worker', [], UrlGeneratorInterface::ABSOLUTE_PATH, '/sw.js'],
            ['nowo_pwa_offline', [], UrlGeneratorInterface::ABSOLUTE_PATH, '/offline'],
        ]);

        return new PwaDataCollector(
            enabled: $enabled,
            manifestConfig: [
                'name'       => 'Demo App',
                'short_name' => 'Demo',
                'display'    => 'standalone',
                'lang'       => 'en',
                'start_url'  => '/',
                'scope'      => '/',
            ],
            serviceWorkerConfig: [
                'enabled'       => true,
                'strategy'      => 'network-first',
                'cache_version' => 'v1',
            ],
            installPromptConfig: ['enabled' => true],
            installLinksConfig: ['enabled' => true],
            clientConfig: ['register_on_load' => true],
            routeTargetingConfig: $routeTargetingConfig,
            routes: [
                'manifest'       => ['path' => '/manifest.webmanifest', 'name' => 'nowo_pwa_manifest'],
                'service_worker' => ['path' => '/sw.js', 'name' => 'nowo_pwa_service_worker'],
                'offline'        => ['path' => '/offline', 'name' => 'nowo_pwa_offline'],
            ],
            routeTargeting: new PwaRouteTargeting(),
            urlGenerator: $urlGenerator,
        );
    }
}
