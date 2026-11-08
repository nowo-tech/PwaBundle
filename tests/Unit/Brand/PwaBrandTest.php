<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Unit\Brand;

use Nowo\Brand\Contract\BrandAsset;
use Nowo\Brand\Contract\BrandAssetOrigin;
use Nowo\Brand\Contract\BrandAssetSlot;
use Nowo\PwaBundle\Brand\BrandManifestOverlayProvider;
use Nowo\PwaBundle\Brand\PwaBrand;
use Nowo\PwaBundle\Tests\Support\BrandFixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;

final class PwaBrandTest extends TestCase
{
    private const MANIFEST = [
        'name'             => 'Configured',
        'short_name'       => 'Conf',
        'background_color' => '#ffffff',
        'theme_color'      => '#0f172a',
        'icons'            => [['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any']],
    ];

    public function testWithoutProviderThereIsNoBrand(): void
    {
        self::assertNull((new PwaBrand())->current());
    }

    public function testEveryCallAsksTheProvider(): void
    {
        $brand = BrandFixtures::brand();
        $pwa   = new PwaBrand(BrandFixtures::provider($brand));

        self::assertSame($brand, $pwa->current());
        self::assertSame($brand, $pwa->current());
    }

    public function testAFailingProviderIsLoggedAndGivesNoBrand(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<array{string, string, array<mixed>}> */
            public array $records = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = [(string) $level, (string) $message, $context];
            }
        };

        $pwa = new PwaBrand(BrandFixtures::failingProvider(), logger: $logger);

        self::assertNull($pwa->current());
        self::assertCount(1, $logger->records);
        self::assertSame('warning', $logger->records[0][0]);
        self::assertStringNotContainsString('database unavailable', $logger->records[0][1]);
        self::assertArrayHasKey('exception', $logger->records[0][2]);
    }

    public function testAFailingProviderWithoutLoggerGivesNoBrand(): void
    {
        self::assertNull((new PwaBrand(BrandFixtures::failingProvider()))->current());
    }

    public function testDefaultBrandKeepsTheConfiguredNamesButAppliesColours(): void
    {
        $manifest = (new PwaBrand())->overlayManifest(self::MANIFEST, BrandFixtures::brand(isDefault: true));

        self::assertSame('Configured', $manifest['name']);
        self::assertSame('Conf', $manifest['short_name']);
        self::assertSame('#1f6f54', $manifest['theme_color']);
        self::assertSame('#f3f6f4', $manifest['background_color']);
        self::assertSame(self::MANIFEST['icons'], $manifest['icons']);
    }

    public function testCustomBrandAppliesItsNames(): void
    {
        $manifest = (new PwaBrand())->overlayManifest(self::MANIFEST, BrandFixtures::brand('Acme Customer Portal'));

        self::assertSame('Acme Customer Portal', $manifest['name']);
        self::assertSame('Acme Custome', $manifest['short_name']);
    }

    public function testOverrideNameAppliesTheDefaultBrandName(): void
    {
        $pwa      = new PwaBrand(overrideName: true);
        $manifest = $pwa->overlayManifest(self::MANIFEST, BrandFixtures::brand('Beacon', isDefault: true));

        self::assertTrue($pwa->appliesName(BrandFixtures::brand(isDefault: true)));
        self::assertSame('Beacon', $manifest['name']);
        self::assertSame('Beacon', $manifest['short_name']);
    }

    public function testAppIconGivesAnyAndMaskableIcons(): void
    {
        $manifest = (new PwaBrand())->overlayManifest(self::MANIFEST, BrandFixtures::appIconBrand());

        self::assertSame([
            ['src' => '/_settings-file/brand/app_icon/0123456789ab.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => '/_settings-file/brand/app_icon/0123456789ab.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
        ], $manifest['icons']);
    }

    public function testSmallerAppIconIsNotMaskable(): void
    {
        $icons = (new PwaBrand())->icons(BrandFixtures::appIconBrand([
            BrandAssetSlot::APP_ICON => BrandFixtures::asset(mimeType: 'image/webp', width: 256, height: 256),
        ]));

        self::assertSame([['src' => '/_settings-file/brand/app_icon/0123456789ab.png', 'sizes' => '256x256', 'type' => 'image/webp', 'purpose' => 'any']], $icons);
    }

    public function testMarkReachedThroughTheFallbackChainIsNotMaskable(): void
    {
        $icons = (new PwaBrand())->icons(BrandFixtures::appIconBrand([
            BrandAssetSlot::MARK => BrandFixtures::asset(BrandAssetSlot::MARK, '/brand/mark.png', width: 1024, height: 1024, origin: BrandAssetOrigin::ConfigDefault),
        ]));

        self::assertSame([['src' => '/brand/mark.png', 'sizes' => '1024x1024', 'type' => 'image/png', 'purpose' => 'any']], $icons);
    }

    /**
     * @return iterable<string, array{array<string, BrandAsset>}>
     */
    public static function unusableIcons(): iterable
    {
        yield 'no asset' => [[]];
        yield 'svg config default' => [[BrandAssetSlot::APP_ICON => BrandFixtures::asset(url: '/brand/app-icon.svg', mimeType: 'image/svg+xml', width: null, height: null, origin: BrandAssetOrigin::ConfigDefault)]];
        yield 'svg with dimensions' => [[BrandAssetSlot::APP_ICON => BrandFixtures::asset(url: '/brand/app-icon.svg', mimeType: 'image/svg+xml', origin: BrandAssetOrigin::ConfigDefault)]];
        yield 'ico config default' => [[BrandAssetSlot::APP_ICON => BrandFixtures::asset(url: '/favicon.ico', mimeType: 'image/x-icon', origin: BrandAssetOrigin::ConfigDefault)]];
        yield 'too small' => [[BrandAssetSlot::APP_ICON => BrandFixtures::asset(width: 128, height: 128)]];
        yield 'not square' => [[BrandAssetSlot::APP_ICON => BrandFixtures::asset(width: 1024, height: 512)]];
        yield 'small mark' => [[BrandAssetSlot::MARK => BrandFixtures::asset(BrandAssetSlot::MARK, width: 256, height: 256)]];
    }

    /**
     * @param array<string, BrandAsset> $assets
     */
    #[DataProvider('unusableIcons')]
    public function testUnusableIconsKeepTheConfiguredIcons(array $assets): void
    {
        $brand    = BrandFixtures::brand(assets: $assets);
        $manifest = (new PwaBrand())->overlayManifest(self::MANIFEST, $brand);

        self::assertSame([], (new PwaBrand())->icons($brand));
        self::assertSame(self::MANIFEST['icons'], $manifest['icons']);
    }

    public function testHttpsIconUrlIsKept(): void
    {
        $icons = (new PwaBrand())->icons(BrandFixtures::appIconBrand([
            BrandAssetSlot::APP_ICON => BrandFixtures::asset(url: 'https://cdn.example.com/icon.png?v=2'),
        ]));

        self::assertSame('https://cdn.example.com/icon.png?v=2', $icons[0]['src']);
    }

    public function testViewContextAppliesTheBrandToTheHeadValues(): void
    {
        $context = (new PwaBrand())->viewContext([
            'manifest'     => self::MANIFEST,
            'meta'         => ['apple_mobile_web_app_title' => 'Old', 'theme_color_light' => '#000000', 'apple_touch_icons' => [['href' => '/icons/apple.png', 'sizes' => '180x180']]],
            'manifest_url' => '/manifest.webmanifest',
            'theme_color'  => '#0f172a',
        ], BrandFixtures::appIconBrand());

        self::assertSame('Acme Portal', $context['manifest']['name']);
        self::assertSame('Acme Portal', $context['manifest']['short_name']);
        self::assertSame('Acme Portal', $context['meta']['apple_mobile_web_app_title']);
        self::assertSame('#f3f6f4', $context['meta']['theme_color_light']);
        self::assertSame('#0c1210', $context['meta']['theme_color_dark']);
        self::assertSame('#1f6f54', $context['theme_color']);
        self::assertSame('#1f6f54', $context['manifest']['theme_color']);
        self::assertSame('#f3f6f4', $context['manifest']['background_color']);
        self::assertCount(1, $context['manifest']['icons']);
        self::assertSame('any', $context['manifest']['icons'][0]['purpose']);
        self::assertSame([['href' => '/_settings-file/brand/app_icon/0123456789ab.png', 'sizes' => '512x512']], $context['meta']['apple_touch_icons']);
        self::assertSame('/manifest.webmanifest?v=' . BrandFixtures::appIconBrand()->version, $context['manifest_url']);
    }

    public function testViewContextOfTheDefaultBrandKeepsNamesAndAppleIconsWhenNoneConfigured(): void
    {
        $context = (new PwaBrand())->viewContext([
            'manifest'     => self::MANIFEST,
            'meta'         => ['apple_mobile_web_app_title' => 'Old', 'apple_touch_icons' => []],
            'manifest_url' => '/pwa/manifest.webmanifest?locale=en',
        ], BrandFixtures::appIconBrand(isDefault: true));

        self::assertSame('Configured', $context['manifest']['name']);
        self::assertSame('Old', $context['meta']['apple_mobile_web_app_title']);
        self::assertSame([], $context['meta']['apple_touch_icons']);
        self::assertStringStartsWith('/pwa/manifest.webmanifest?locale=en&v=', $context['manifest_url']);
    }

    public function testViewContextToleratesAMissingManifestAndMeta(): void
    {
        $context = (new PwaBrand())->viewContext([], BrandFixtures::brand());

        self::assertSame('#f3f6f4', $context['meta']['theme_color_light']);
        self::assertSame('Acme Portal', $context['manifest']['name']);
        self::assertArrayNotHasKey('manifest_url', $context);
        self::assertArrayNotHasKey('icons', $context['manifest']);
    }

    public function testOverlayProviderWithoutBrandReturnsTheManifestUnchanged(): void
    {
        self::assertSame(self::MANIFEST, (new BrandManifestOverlayProvider(new PwaBrand()))->overlay(self::MANIFEST));
        self::assertSame(self::MANIFEST, (new BrandManifestOverlayProvider(new PwaBrand(BrandFixtures::failingProvider())))->overlay(self::MANIFEST));
    }

    public function testOverlayProviderAppliesTheBrand(): void
    {
        $overlay = new BrandManifestOverlayProvider(new PwaBrand(BrandFixtures::provider(BrandFixtures::brand())));

        self::assertSame('Acme Portal', $overlay->overlay(self::MANIFEST)['name']);
    }
}
