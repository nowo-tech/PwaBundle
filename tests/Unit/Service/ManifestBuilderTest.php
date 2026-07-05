<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Unit\Service;

use Nowo\PwaBundle\Service\ManifestBuilder;
use PHPUnit\Framework\TestCase;

final class ManifestBuilderTest extends TestCase
{
    public function testBuildsManifestWithIconsAndShortcuts(): void
    {
        $builder = new ManifestBuilder();
        $result  = $builder->build([
            'name'             => 'Demo App',
            'short_name'       => 'Demo',
            'description'      => 'A demo PWA',
            'lang'             => 'en',
            'dir'              => 'ltr',
            'scope'            => '/',
            'display'          => 'standalone',
            'orientation'      => 'portrait',
            'theme_color'      => '#111111',
            'background_color' => '#ffffff',
            'categories'       => ['productivity'],
            'icons'            => [
                ['src' => '/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
            ],
            'shortcuts' => [
                ['name' => 'Home', 'url' => '/'],
            ],
        ], 'https://example.test/');

        self::assertSame('Demo App', $result['name']);
        self::assertSame('https://example.test/', $result['start_url']);
        self::assertSame('productivity', $result['categories'][0]);
        self::assertSame('/icon-192.png', $result['icons'][0]['src']);
        self::assertSame('Home', $result['shortcuts'][0]['name']);
    }

    public function testShortcutOptionalFieldsAndSkipsInvalidIcons(): void
    {
        $builder = new ManifestBuilder();
        $result  = $builder->build([
            'icons' => [
                'not-an-array',
                ['src' => '', 'sizes' => '192x192'],
                ['src' => '/ok.png'],
            ],
            'shortcuts' => [
                [
                    'name'        => 'Docs',
                    'short_name'  => 'Docs',
                    'url'         => '/docs',
                    'description' => 'Documentation',
                ],
            ],
        ], '/');

        self::assertCount(1, $result['icons']);
        self::assertSame('Docs', $result['shortcuts'][0]['short_name']);
        self::assertSame('Documentation', $result['shortcuts'][0]['description']);
    }
}
