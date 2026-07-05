<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Unit\Service;

use Nowo\PwaBundle\Service\ManifestBuilder;
use PHPUnit\Framework\TestCase;

final class ManifestBuilderEdgeCasesTest extends TestCase
{
    public function testSkipsEmptyDescriptionAndAnyOrientation(): void
    {
        $result = (new ManifestBuilder())->build([
            'name'        => 'App',
            'short_name'  => 'App',
            'description' => '',
            'orientation' => 'any',
            'icons'       => 'invalid',
            'shortcuts'   => [['name' => '', 'url' => '/']],
        ], 'https://example.test/');

        self::assertArrayNotHasKey('description', $result);
        self::assertArrayNotHasKey('orientation', $result);
        self::assertArrayNotHasKey('icons', $result);
        self::assertArrayNotHasKey('shortcuts', $result);
    }

    public function testSkipsInvalidShortcutEntries(): void
    {
        $result = (new ManifestBuilder())->build([
            'shortcuts' => 'invalid',
        ], '/');

        self::assertArrayNotHasKey('shortcuts', $result);

        $result2 = (new ManifestBuilder())->build([
            'shortcuts' => [['name' => 'X', 'url' => '/x', 'short_name' => 123]],
        ], '/');

        self::assertSame('X', $result2['shortcuts'][0]['name']);
        self::assertArrayNotHasKey('short_name', $result2['shortcuts'][0]);

        $result3 = (new ManifestBuilder())->build([
            'shortcuts' => ['invalid-entry', ['name' => 'Ok', 'url' => '/ok']],
        ], '/');

        self::assertCount(1, $result3['shortcuts']);
    }

    public function testSkipsInvalidExtendedSections(): void
    {
        $builder = new ManifestBuilder();
        $result  = $builder->build([
            'display_override'            => ['', 'standalone'],
            'prefer_related_applications' => false,
            'iarc_rating_id'              => '',
            'screenshots'                 => ['bad', ['src' => '/ok.png']],
            'related_applications'        => [['platform' => ''], ['platform' => 'play', 'url' => 'https://play.example']],
            'scope_extensions'            => 'invalid',
            'launch_handler'              => ['client_mode' => 'auto'],
            'protocol_handlers'           => [['protocol' => '', 'url' => '/']],
            'file_handlers'               => [
                ['action' => '/open', 'accept_map' => []],
                ['action' => '', 'accept_map' => ['text/plain' => ['.txt']]],
            ],
            'share_target'    => ['action' => ''],
            'edge_side_panel' => ['preferred_width' => 'invalid'],
        ], '/');

        self::assertSame(['standalone'], $result['display_override']);
        self::assertArrayNotHasKey('prefer_related_applications', $result);
        self::assertArrayNotHasKey('iarc_rating_id', $result);
        self::assertCount(1, $result['screenshots']);
        self::assertSame('play', $result['related_applications'][0]['platform']);
        self::assertArrayNotHasKey('scope_extensions', $result);
        self::assertArrayNotHasKey('launch_handler', $result);
        self::assertArrayNotHasKey('protocol_handlers', $result);
        self::assertArrayNotHasKey('file_handlers', $result);
        self::assertArrayNotHasKey('share_target', $result);
        self::assertArrayNotHasKey('edge_side_panel', $result);
    }

    public function testShareTargetGetMethodOmitsMethodKey(): void
    {
        $result = (new ManifestBuilder())->build([
            'share_target' => [
                'action' => '/share',
                'method' => 'GET',
                'params' => ['text' => 'text'],
            ],
        ], '/');

        self::assertSame('/share', $result['share_target']['action']);
        self::assertArrayNotHasKey('method', $result['share_target']);
        self::assertSame('text', $result['share_target']['params']['text']);
    }

    public function testAcceptMapSkipsInvalidEntries(): void
    {
        $result = (new ManifestBuilder())->build([
            'file_handlers' => [
                [
                    'action'     => '/open',
                    'accept_map' => ['' => ['.txt'], 'text/plain' => ['', '.txt'], 123 => ['.bad']],
                ],
            ],
        ], '/');

        self::assertSame(['.txt'], $result['file_handlers'][0]['accept']['text/plain']);
    }

    public function testIncludesOptionalRelatedIdShareEnctypeAndIcons(): void
    {
        $result = (new ManifestBuilder())->build([
            'related_applications' => [
                ['platform' => 'itunes', 'id' => '123456'],
            ],
            'share_target' => [
                'action'  => '/share',
                'method'  => 'POST',
                'enctype' => 'application/x-www-form-urlencoded',
                'params'  => ['files' => 'files'],
            ],
            'file_handlers' => [
                [
                    'action'     => '/import',
                    'accept_map' => ['text/csv' => ['.csv']],
                    'icons'      => [['src' => '/csv.png']],
                ],
            ],
            'edge_side_panel' => ['preferred_width' => '480'],
            'categories'      => 'invalid',
        ], '/');

        self::assertSame('123456', $result['related_applications'][0]['id']);
        self::assertArrayNotHasKey('url', $result['related_applications'][0]);
        self::assertSame('application/x-www-form-urlencoded', $result['share_target']['enctype']);
        self::assertSame('files', $result['share_target']['params']['files']);
        self::assertSame('/csv.png', $result['file_handlers'][0]['icons'][0]['src']);
        self::assertSame(480, $result['edge_side_panel']['preferred_width']);
        self::assertArrayNotHasKey('categories', $result);
    }

    public function testSkipsNonListAndInvalidNestedEntries(): void
    {
        $result = (new ManifestBuilder())->build([
            'display_override'     => 'not-an-array',
            'screenshots'          => ['src' => '/ignored.png'],
            'related_applications' => ['platform' => 'web'],
            'scope_extensions'     => [
                'invalid',
                ['origin' => ''],
            ],
            'protocol_handlers' => 'invalid',
            'file_handlers'     => ['action' => '/ignored'],
        ], '/');

        self::assertArrayNotHasKey('display_override', $result);
        self::assertArrayNotHasKey('screenshots', $result);
        self::assertArrayNotHasKey('related_applications', $result);
        self::assertArrayNotHasKey('scope_extensions', $result);
        self::assertArrayNotHasKey('protocol_handlers', $result);
        self::assertArrayNotHasKey('file_handlers', $result);

        $result2 = (new ManifestBuilder())->build([
            'screenshots' => [
                ['src' => ''],
                ['src' => '/valid.png'],
            ],
            'related_applications' => [
                'invalid',
                ['platform' => 'play', 'url' => 'https://play.example'],
            ],
            'protocol_handlers' => [
                'invalid',
                ['protocol' => 'mailto', 'url' => '/mail?to=%s'],
            ],
            'file_handlers' => [
                [
                    'action'     => '/open',
                    'accept_map' => ['text/plain' => ['.txt']],
                ],
            ],
        ], '/');

        self::assertCount(1, $result2['screenshots']);
        self::assertSame('/valid.png', $result2['screenshots'][0]['src']);
        self::assertSame('play', $result2['related_applications'][0]['platform']);
        self::assertSame('mailto', $result2['protocol_handlers'][0]['protocol']);

        $result3 = (new ManifestBuilder())->build([
            'file_handlers' => [
                'invalid',
                [
                    'action'     => '/open',
                    'accept_map' => 'invalid',
                ],
            ],
        ], '/');

        self::assertArrayNotHasKey('file_handlers', $result3);
    }
}
