<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Brand;

use Nowo\Brand\Contract\Brand;
use Nowo\PwaBundle\Service\ManifestOverlayProviderInterface;

/**
 * Applies the brand of a `nowo-tech/brand-contracts` provider to the web manifest (spec 220 S4, see {@see PwaBrand}).
 *
 * Tagged `nowo_pwa.manifest_overlay_provider` with priority {@see PwaBrand::OVERLAY_PRIORITY}, so it runs before
 * host overlays (priority 0 by default) and they keep the last word. Without a brand the manifest is returned unchanged.
 */
final readonly class BrandManifestOverlayProvider implements ManifestOverlayProviderInterface
{
    public function __construct(
        private PwaBrand $brand,
    ) {
    }

    public function overlay(array $manifest): array
    {
        $brand = $this->brand->current();

        return $brand instanceof Brand ? $this->brand->overlayManifest($manifest, $brand) : $manifest;
    }
}
