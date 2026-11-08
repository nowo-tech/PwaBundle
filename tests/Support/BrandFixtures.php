<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Support;

use Nowo\Brand\Contract\Brand;
use Nowo\Brand\Contract\BrandAsset;
use Nowo\Brand\Contract\BrandAssetOrigin;
use Nowo\Brand\Contract\BrandAssetSlot;
use Nowo\Brand\Contract\BrandColors;
use Nowo\Brand\Contract\BrandProviderInterface;
use RuntimeException;

use function hash;

/**
 * Brand-contract values for the spec 220 S4 tests (light paper #f3f6f4, dark paper #0c1210, primary #1f6f54).
 */
final class BrandFixtures
{
    /**
     * @param array<string, BrandAsset> $assets
     */
    public static function brand(string $name = 'Acme Portal', bool $isDefault = false, array $assets = [], ?string $shortName = null): Brand
    {
        return new Brand(
            $name,
            new BrandColors('#1f6f54', '#134736', '#b42318', '#b54708', '#f3f6f4', '#0f1c18', '#ffffff'),
            new BrandColors('#4aad7f', '#6bc49a', '#f97066', '#fdb022', '#0c1210', '#e6eee9', '#151c19'),
            $assets,
            $shortName,
            isDefault: $isDefault,
        );
    }

    public static function asset(
        string $slot = BrandAssetSlot::APP_ICON,
        string $url = '/_settings-file/brand/app_icon/0123456789ab.png',
        string $mimeType = 'image/png',
        ?int $width = 512,
        ?int $height = 512,
        BrandAssetOrigin $origin = BrandAssetOrigin::Uploaded,
    ): BrandAsset {
        return new BrandAsset($slot, $url, $mimeType, $width, $height, hash('sha256', $slot . $url), $origin);
    }

    /**
     * @param array<string, BrandAsset> $assets
     */
    public static function appIconBrand(array $assets = [], bool $isDefault = false): Brand
    {
        return self::brand(isDefault: $isDefault, assets: $assets === [] ? [BrandAssetSlot::APP_ICON => self::asset()] : $assets);
    }

    public static function provider(Brand $brand): BrandProviderInterface
    {
        return new class($brand) implements BrandProviderInterface {
            public function __construct(private readonly Brand $brand)
            {
            }

            public function getBrand(): Brand
            {
                return $this->brand;
            }
        };
    }

    public static function failingProvider(): BrandProviderInterface
    {
        return new class implements BrandProviderInterface {
            public function getBrand(): Brand
            {
                throw new RuntimeException('database unavailable');
            }
        };
    }
}
