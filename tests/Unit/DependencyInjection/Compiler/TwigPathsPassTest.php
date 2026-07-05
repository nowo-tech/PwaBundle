<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\PwaBundle\DependencyInjection\Compiler\TwigPathsPass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class TwigPathsPassTest extends TestCase
{
    public function testAddsBundleTwigPath(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('twig.loader.native', new Definition(stdClass::class));
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());

        (new TwigPathsPass())->process($container);

        $calls = $container->getDefinition('twig.loader.native')->getMethodCalls();
        self::assertNotEmpty($calls);
    }

    public function testPrependsOverridePathWhenDirectoryExists(): void
    {
        $projectDir = sys_get_temp_dir() . '/pwa_twig_' . uniqid('', true);
        $override   = $projectDir . '/templates/bundles/NowoPwaBundle';
        mkdir($override, 0777, true);

        $container = new ContainerBuilder();
        $container->setDefinition('twig.loader.native', new Definition(stdClass::class));
        $container->setParameter('kernel.project_dir', $projectDir);

        (new TwigPathsPass())->process($container);

        $calls = $container->getDefinition('twig.loader.native')->getMethodCalls();
        self::assertSame('prependPath', $calls[0][0]);
        self::assertSame($override, $calls[0][1][0]);
    }

    public function testResolvesLoaderAlias(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('twig.loader.native_filesystem', new Definition(stdClass::class));
        $container->setAlias('twig.loader.native', new Alias('twig.loader.native_filesystem'));
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());

        (new TwigPathsPass())->process($container);

        self::assertNotEmpty($container->getDefinition('twig.loader.native_filesystem')->getMethodCalls());
    }

    public function testSkipsWhenNoLoader(): void
    {
        $container = new ContainerBuilder();
        (new TwigPathsPass())->process($container);
        self::assertFalse($container->hasDefinition('twig.loader.native'));
    }

    public function testResolvesChainedLoaderAlias(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('twig.loader.target', new Definition(stdClass::class));
        $container->setAlias('twig.loader.native', new Alias('twig.loader.chain'));
        $container->setAlias('twig.loader.chain', new Alias('twig.loader.target'));
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());

        (new TwigPathsPass())->process($container);

        self::assertNotEmpty($container->getDefinition('twig.loader.target')->getMethodCalls());
    }

    public function testIgnoresBrokenLoaderAlias(): void
    {
        $container = new ContainerBuilder();
        $container->setAlias('twig.loader.native', new Alias('twig.loader.missing'));
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());

        (new TwigPathsPass())->process($container);

        self::assertFalse($container->hasDefinition('twig.loader.missing'));
    }

    public function testUsesNativeFilesystemLoaderDirectly(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('twig.loader.native_filesystem', new Definition(stdClass::class));
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());

        (new TwigPathsPass())->process($container);

        self::assertNotEmpty($container->getDefinition('twig.loader.native_filesystem')->getMethodCalls());
    }
}
