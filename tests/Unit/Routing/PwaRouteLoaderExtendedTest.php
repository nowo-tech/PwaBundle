<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Unit\Routing;

use Nowo\PwaBundle\Routing\PwaRouteLoader;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PwaRouteLoaderExtendedTest extends TestCase
{
    private function routes(): array
    {
        return [
            'manifest'       => ['path' => '/manifest.webmanifest', 'name' => 'nowo_pwa_manifest'],
            'service_worker' => ['path' => '/sw.js', 'name' => 'nowo_pwa_service_worker'],
            'offline'        => ['path' => '/offline', 'name' => 'nowo_pwa_offline'],
        ];
    }

    public function testAppliesRoutePrefix(): void
    {
        $loader = new PwaRouteLoader($this->routes(), '/app');
        $route  = $loader->load('.', 'nowo_pwa')->get('nowo_pwa_manifest');
        self::assertNotNull($route);
        self::assertSame('/app/manifest.webmanifest', $route->getPath());
    }

    public function testCannotLoadTwice(): void
    {
        $loader = new PwaRouteLoader($this->routes(), '');
        $loader->load('.', 'nowo_pwa');
        $this->expectException(RuntimeException::class);
        $loader->load('.', 'nowo_pwa');
    }
}
