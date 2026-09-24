<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Unit\Routing;

use Nowo\PwaBundle\Routing\PwaRouteLoader;
use PHPUnit\Framework\TestCase;

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

    public function testLoadIsIdempotent(): void
    {
        $loader = new PwaRouteLoader($this->routes(), '');
        $first  = $loader->load('.', 'nowo_pwa');
        $second = $loader->load('.', 'nowo_pwa');

        self::assertNotSame($first, $second);
        self::assertNotNull($second->get('nowo_pwa_manifest'));
        self::assertSame('/manifest.webmanifest', $second->get('nowo_pwa_manifest')?->getPath());
    }
}
