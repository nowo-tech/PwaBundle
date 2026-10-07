<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Service;

/**
 * Lets host applications overlay values (brand, colors, icons, ...) onto the web manifest.
 *
 * Implementations are autoconfigured with the `nowo_pwa.manifest_overlay_provider` tag and are
 * applied in service priority order after {@see ManifestBuilder::build()}.
 */
interface ManifestOverlayProviderInterface
{
    public const TAG = 'nowo_pwa.manifest_overlay_provider';

    /**
     * @param array<string, mixed> $manifest Manifest built so far
     *
     * @return array<string, mixed> Manifest with overlay applied (return the input unchanged to skip)
     */
    public function overlay(array $manifest): array;
}
