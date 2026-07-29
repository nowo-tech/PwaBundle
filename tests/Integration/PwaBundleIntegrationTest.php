<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Integration;

use Nowo\PwaBundle\DataCollector\PwaDataCollector;
use Nowo\PwaBundle\DependencyInjection\PwaExtension;
use Nowo\PwaBundle\PwaBundle;
use Nowo\PwaBundle\Routing\PwaRouteLoader;
use Nowo\PwaBundle\Service\ManifestBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class PwaBundleIntegrationTest extends TestCase
{
    public function testExtensionLoadsServices(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', true);
        (new PwaExtension())->load([[]], $container);

        self::assertTrue($container->hasDefinition(ManifestBuilder::class));
        self::assertTrue($container->hasDefinition(PwaRouteLoader::class));
        self::assertTrue($container->hasDefinition(PwaDataCollector::class));
        self::assertSame('nowo_pwa', (new PwaBundle())->getContainerExtension()->getAlias());
    }

    public function testExtensionSkipsDataCollectorWhenDebugIsDisabled(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        (new PwaExtension())->load([[]], $container);

        self::assertFalse($container->hasDefinition(PwaDataCollector::class));
    }
}
