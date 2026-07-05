<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Unit;

use Nowo\PwaBundle\PwaBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class PwaBundleTest extends TestCase
{
    public function testTranslationDomain(): void
    {
        self::assertSame('NowoPwaBundle', PwaBundle::TRANSLATION_DOMAIN);
    }

    public function testBuildRegistersCompilerPass(): void
    {
        $container = new ContainerBuilder();
        (new PwaBundle())->build($container);
        self::assertNotEmpty($container->getCompilerPassConfig()->getPasses());
    }

    public function testGetContainerExtensionIsSingleton(): void
    {
        $bundle = new PwaBundle();
        self::assertSame($bundle->getContainerExtension(), $bundle->getContainerExtension());
    }
}
