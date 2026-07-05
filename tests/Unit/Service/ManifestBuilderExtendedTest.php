<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Unit\Service;

use Nowo\PwaBundle\Service\ManifestBuilder;
use PHPUnit\Framework\TestCase;

final class ManifestBuilderExtendedTest extends TestCase
{
    public function testBuildsExtendedManifestSections(): void
    {
        $result = (new ManifestBuilder())->build([
            'display_override'            => ['standalone', 'browser'],
            'prefer_related_applications' => true,
            'iarc_rating_id'              => 'e84b072d-71b3-4071-8c3c-0445f7703fc9',
            'screenshots'                 => [
                ['src' => '/shot.png', 'sizes' => '1280x720', 'form_factor' => 'wide', 'label' => 'Home'],
            ],
            'related_applications' => [
                ['platform' => 'webapp', 'url' => 'https://example.test/manifest.webmanifest'],
            ],
            'scope_extensions' => [
                ['origin' => 'https://cdn.example.test'],
            ],
            'launch_handler'    => ['client_mode' => 'navigate-existing'],
            'protocol_handlers' => [
                ['protocol' => 'web+pwa', 'url' => '/open?url=%s'],
            ],
            'file_handlers' => [
                [
                    'action'     => '/import',
                    'accept_map' => ['application/json' => ['.json']],
                    'icons'      => [['src' => '/icon.png']],
                ],
            ],
            'share_target' => [
                'action'  => '/share',
                'method'  => 'POST',
                'enctype' => 'multipart/form-data',
                'params'  => ['title' => 'title', 'url' => 'url'],
            ],
            'edge_side_panel' => ['preferred_width' => 400],
            'shortcuts'       => [
                [
                    'name'  => 'Inbox',
                    'url'   => '/inbox',
                    'icons' => [['src' => '/shortcut.png', 'sizes' => '96x96']],
                ],
            ],
        ], 'https://example.test/app');

        self::assertSame(['standalone', 'browser'], $result['display_override']);
        self::assertTrue($result['prefer_related_applications']);
        self::assertSame('wide', $result['screenshots'][0]['form_factor']);
        self::assertSame('navigate-existing', $result['launch_handler']['client_mode']);
        self::assertSame('web+pwa', $result['protocol_handlers'][0]['protocol']);
        self::assertSame(['application/json' => ['.json']], $result['file_handlers'][0]['accept']);
        self::assertSame('/share', $result['share_target']['action']);
        self::assertSame(400, $result['edge_side_panel']['preferred_width']);
        self::assertSame('/shortcut.png', $result['shortcuts'][0]['icons'][0]['src']);
    }

    public function testRelativeStartUrlWhenConfigured(): void
    {
        $result = (new ManifestBuilder())->build(['start_url' => '/app'], '/app');
        self::assertSame('/app', $result['start_url']);
    }
}
