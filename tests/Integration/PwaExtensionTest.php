<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Integration;

use Nowo\PwaBundle\DependencyInjection\PwaExtension;
use Nowo\PwaBundle\Twig\PwaTwigExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class PwaExtensionTest extends TestCase
{
    public function testPrependConfiguresAssets(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new FrameworkExtension());
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

    public function testWebPushMergesKitAppendScript(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        (new PwaExtension())->load([[
            'service_worker' => [
                'web_push'          => true,
                'web_push_defaults' => [
                    'title' => "O'Brien",
                    'url'   => '/dashboard',
                    'tag'   => 'beacon',
                ],
            ],
        ]], $container);

        /** @var array<string, mixed> $sw */
        $sw     = $container->getParameter('nowo_pwa.service_worker');
        $append = (string) ($sw['append_script'] ?? '');

        self::assertStringContainsString('nowo-pwa-web-push', $append);
        self::assertStringContainsString("self.addEventListener('push'", $append);
        self::assertStringContainsString("self.addEventListener('notificationclick'", $append);
        self::assertStringContainsString("title: 'O\\'Brien'", $append);
        self::assertStringContainsString("url: '/dashboard'", $append);
        self::assertStringContainsString("tag: 'beacon'", $append);
    }

    public function testHostAppendScriptFollowsKitWebPush(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        (new PwaExtension())->load([[
            'service_worker' => [
                'web_push'      => true,
                'append_script' => '/* host extra */',
            ],
        ]], $container);

        /** @var array<string, mixed> $sw */
        $sw     = $container->getParameter('nowo_pwa.service_worker');
        $append = (string) ($sw['append_script'] ?? '');

        self::assertStringContainsString('nowo-pwa-web-push', $append);
        self::assertStringContainsString('/* host extra */', $append);
        $kitPos  = strpos($append, 'nowo-pwa-web-push');
        $hostPos = strpos($append, '/* host extra */');
        self::assertNotFalse($kitPos);
        self::assertNotFalse($hostPos);
        self::assertLessThan($hostPos, $kitPos);
    }
}
