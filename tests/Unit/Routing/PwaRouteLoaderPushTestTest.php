<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Unit\Routing;

use Nowo\PwaBundle\Controller\PushTestController;
use Nowo\PwaBundle\Routing\PwaRouteLoader;
use PHPUnit\Framework\TestCase;

final class PwaRouteLoaderPushTestTest extends TestCase
{
    private const ROUTES = [
        'manifest'       => ['path' => '/manifest.webmanifest', 'name' => 'nowo_pwa_manifest'],
        'service_worker' => ['path' => '/sw.js', 'name' => 'nowo_pwa_service_worker'],
        'offline'        => ['path' => '/offline', 'name' => 'nowo_pwa_offline'],
        'push_test'      => ['path' => '/pwa/push/test', 'name' => 'nowo_pwa_push_test'],
    ];

    public function testPushTestRouteOnlyWhenEnabled(): void
    {
        self::assertNull((new PwaRouteLoader(self::ROUTES, ''))->load('.', 'nowo_pwa')->get('nowo_pwa_push_test'));

        $route = (new PwaRouteLoader(self::ROUTES, '/app', true))->load('.', 'nowo_pwa')->get('nowo_pwa_push_test');
        self::assertNotNull($route);
        self::assertSame('/app/pwa/push/test', $route->getPath());
        self::assertSame(['POST'], $route->getMethods());
        self::assertSame(PushTestController::class, $route->getDefault('_controller'));
    }

    public function testMissingPushTestRouteConfigIsIgnored(): void
    {
        $routes = self::ROUTES;
        unset($routes['push_test']);

        self::assertCount(3, (new PwaRouteLoader($routes, '', true))->load('.', 'nowo_pwa'));
    }
}
