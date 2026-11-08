<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Brand;

use Nowo\Brand\Contract\Brand;
use Nowo\Brand\Contract\BrandAsset;
use Nowo\Brand\Contract\BrandAssetSlot;
use Nowo\Brand\Contract\BrandProviderInterface;
use Psr\Log\LoggerInterface;
use Throwable;

use function in_array;
use function is_array;
use function is_string;
use function rawurlencode;
use function sprintf;
use function str_contains;

/**
 * Reads the brand of a `nowo-tech/brand-contracts` {@see BrandProviderInterface} for the manifest and the head tags
 * (spec 220 S4, PwaBundle 1.15).
 *
 * Registered only with `nowo_pwa.brand.mode: auto` (the default) and brand-contracts installed; the provider is an
 * optional reference, so without a provider service {@see self::current()} is null and every output stays the
 * `nowo_pwa` configuration, byte for byte.
 *
 * From the brand: `name` and `short_name` (only when the brand is not the default one, unless
 * `brand.override_name: true`), `primary` (light) as `theme_color`, `paper` (light) as `background_color`, `paper`
 * light / dark as the `theme-color` meta tags, and the `app_icon` slot (after the contract's fallback chain) as the
 * manifest icons when it is a raster image (PNG, JPEG, WebP), square and at least 192 px. Anything else keeps the
 * configured icons. SVG is never used as an icon, whatever its origin.
 *
 * Stateless: every call asks the provider again (providers memoise per request themselves), so a long-running worker
 * keeps nothing between requests. A failing provider is logged and the configuration applies; the manifest and the
 * page never fail because of the brand.
 */
final readonly class PwaBrand
{
    /** Priority of the brand manifest overlay: it runs before host overlays (priority 0), so they can override it. */
    public const OVERLAY_PRIORITY = 1024;

    /** Smallest square icon side taken from the brand. */
    public const ICON_MIN_SIDE = 192;

    /** Smallest `app_icon` side also declared as a maskable icon. */
    public const MASKABLE_MIN_SIDE = 512;

    public function __construct(
        private ?BrandProviderInterface $provider = null,
        private bool $overrideName = false,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * The current brand, or null without a provider or when the provider fails.
     */
    public function current(): ?Brand
    {
        if (!$this->provider instanceof BrandProviderInterface) {
            return null;
        }

        try {
            return $this->provider->getBrand();
        } catch (Throwable $e) {
            $this->logger?->warning('PwaBundle: the brand provider failed, using the nowo_pwa configuration.', ['exception' => $e]);

            return null;
        }
    }

    /**
     * Whether the brand name replaces the configured `name` / `short_name`.
     */
    public function appliesName(Brand $brand): bool
    {
        return $this->overrideName || !$brand->isDefault;
    }

    /**
     * Manifest icons from the `app_icon` slot: one `any` entry and, for an `app_icon` of at least 512 px, one
     * `maskable` entry (the slot is designed with the maskable safe zone; a `mark` reached through the fallback chain
     * is not). Empty when the slot does not resolve to a usable raster icon.
     *
     * @return list<array{src: string, sizes: string, type: string, purpose: string}>
     */
    public function icons(Brand $brand): array
    {
        $asset = $brand->asset(BrandAssetSlot::APP_ICON);
        if (!$asset instanceof BrandAsset || !$this->isUsableIcon($asset)) {
            return [];
        }

        $icon  = ['src' => $asset->url, 'sizes' => sprintf('%dx%d', $asset->width, $asset->height), 'type' => $asset->mimeType];
        $icons = [$icon + ['purpose' => 'any']];
        if ($asset->slot === BrandAssetSlot::APP_ICON && $asset->minSide() >= self::MASKABLE_MIN_SIDE) {
            $icons[] = $icon + ['purpose' => 'maskable'];
        }

        return $icons;
    }

    /**
     * The manifest with the brand applied (see the class description).
     *
     * @param array<string, mixed> $manifest
     *
     * @return array<string, mixed>
     */
    public function overlayManifest(array $manifest, Brand $brand): array
    {
        if ($this->appliesName($brand)) {
            $manifest['name']       = $brand->name;
            $manifest['short_name'] = $brand->shortName;
        }
        $manifest['theme_color']      = $brand->light->primary;
        $manifest['background_color'] = $brand->light->paper;

        $icons = $this->icons($brand);
        if ($icons !== []) {
            $manifest['icons'] = $icons;
        }

        return $manifest;
    }

    /**
     * The view context of the bundle templates (head, install prompt, install links) with the brand applied: the
     * same manifest values, `meta.theme_color_light` / `_dark` from `paper`, the Apple title from the short name, the
     * brand icon as the `apple-touch-icon` (only where the configuration declares one) and the manifest URL carrying
     * the brand version, so browsers fetch a new manifest as soon as the brand changes.
     *
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function viewContext(array $context, Brand $brand): array
    {
        $manifest = is_array($context['manifest'] ?? null) ? $context['manifest'] : [];
        $meta     = is_array($context['meta'] ?? null) ? $context['meta'] : [];

        if ($this->appliesName($brand)) {
            $manifest['name']                   = $brand->name;
            $manifest['short_name']             = $brand->shortName;
            $meta['apple_mobile_web_app_title'] = $brand->shortName;
        }
        $manifest['theme_color']      = $brand->light->primary;
        $manifest['background_color'] = $brand->light->paper;
        $meta['theme_color_light']    = $brand->light->paper;
        $meta['theme_color_dark']     = $brand->dark->paper;

        $icons = $this->icons($brand);
        if ($icons !== []) {
            // The head prints every manifest icon as <link rel="icon">: the maskable copy is left out there.
            $manifest['icons'] = [$icons[0]];
            if (is_array($meta['apple_touch_icons'] ?? null) && $meta['apple_touch_icons'] !== []) {
                $meta['apple_touch_icons'] = [['href' => $icons[0]['src'], 'sizes' => $icons[0]['sizes']]];
            }
        }

        $context['manifest']    = $manifest;
        $context['meta']        = $meta;
        $context['theme_color'] = $brand->light->primary;

        $url = $context['manifest_url'] ?? null;
        if (is_string($url)) {
            $context['manifest_url'] = $url . (str_contains($url, '?') ? '&' : '?') . 'v=' . rawurlencode($brand->version);
        }

        return $context;
    }

    private function isUsableIcon(BrandAsset $asset): bool
    {
        return in_array($asset->mimeType, BrandAsset::UPLOAD_MIME_TYPES, true)
            && $asset->isSquare()
            && $asset->minSide() >= self::ICON_MIN_SIDE
            && BrandAsset::isSafeUrl($asset->url);
    }
}
