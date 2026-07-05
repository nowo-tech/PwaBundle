<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Unit\Routing;

use Nowo\PwaBundle\Routing\PwaRouteLoader;
use PHPUnit\Framework\TestCase;

final class PwaRouteLoaderTest extends TestCase
{
    public function testLoadsRoutes(): void
    {
        $loader = new PwaRouteLoader([
            'manifest'       => ['path' => '/manifest.webmanifest', 'name' => 'nowo_pwa_manifest'],
            'service_worker' => ['path' => '/sw.js', 'name' => 'nowo_pwa_service_worker'],
            'offline'        => ['path' => '/offline', 'name' => 'nowo_pwa_offline'],
        ], '');

        $collection = $loader->load('.', 'nowo_pwa');

        self::assertNotNull($collection->get('nowo_pwa_manifest'));
        self::assertTrue($loader->supports('.', 'nowo_pwa'));
        self::assertFalse($loader->supports('.', 'other'));
    }
}
