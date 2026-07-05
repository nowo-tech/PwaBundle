<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Integration;

use Nowo\PwaBundle\DependencyInjection\PwaExtension;
use Nowo\PwaBundle\Twig\PwaTwigExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class PwaExtensionTest extends TestCase
{
    public function testPrependConfiguresAssets(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new \Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension());
        (new PwaExtension())->prepend($container);
        $configs = $container->getExtensionConfig('framework');
        self::assertSame('/bundles/pwa', $configs[0]['assets']['packages']['nowo_pwa']['base_path']);
    }

    public function testPrependSkipsWithoutFramework(): void
    {
        $container = new ContainerBuilder();
        (new PwaExtension())->prepend($container);
        self::assertFalse($container->hasExtension('framework'));
    }

    public function testLoadsTwigExtensionService(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        (new PwaExtension())->load([[]], $container);
        self::assertTrue($container->hasDefinition(PwaTwigExtension::class));
    }
}
