<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Service;

use function array_is_list;
use function is_array;
use function is_int;
use function is_string;

/**
 * Builds a W3C Web App Manifest array from bundle configuration.
 */
final class ManifestBuilder
{
    /**
     * @param array<string, mixed> $manifestConfig
     *
     * @return array<string, mixed>
     */
    public function build(array $manifestConfig, string $startUrl): array
    {
        $manifest = [
            'name'             => (string) ($manifestConfig['name'] ?? 'App'),
            'short_name'       => (string) ($manifestConfig['short_name'] ?? 'App'),
            'start_url'        => $startUrl,
            'scope'            => (string) ($manifestConfig['scope'] ?? '/'),
            'display'          => (string) ($manifestConfig['display'] ?? 'standalone'),
            'background_color' => (string) ($manifestConfig['background_color'] ?? '#ffffff'),
            'theme_color'      => (string) ($manifestConfig['theme_color'] ?? '#0f172a'),
            'id'               => (string) ($manifestConfig['id'] ?? $startUrl),
        ];

        $this->applyOptionalScalar($manifest, 'description', $manifestConfig['description'] ?? '');
        $this->applyOptionalScalar($manifest, 'lang', $manifestConfig['lang'] ?? '');
        $this->applyOptionalScalar($manifest, 'dir', $manifestConfig['dir'] ?? '');

        $orientation = (string) ($manifestConfig['orientation'] ?? '');
        if ($orientation !== '' && $orientation !== 'any') {
            $manifest['orientation'] = $orientation;
        }

        $displayOverride = $this->stringList($manifestConfig['display_override'] ?? []);
        if ($displayOverride !== []) {
            $manifest['display_override'] = $displayOverride;
        }

        $icons = $this->normalizeIcons($manifestConfig['icons'] ?? []);
        if ($icons !== []) {
            $manifest['icons'] = $icons;
        }

        $screenshots = $this->normalizeScreenshots($manifestConfig['screenshots'] ?? []);
        if ($screenshots !== []) {
            $manifest['screenshots'] = $screenshots;
        }

        $shortcuts = $this->normalizeShortcuts($manifestConfig['shortcuts'] ?? []);
        if ($shortcuts !== []) {
            $manifest['shortcuts'] = $shortcuts;
        }

        $categories = $manifestConfig['categories'] ?? [];
        if (is_array($categories) && $categories !== []) {
            $manifest['categories'] = array_values(array_map(strval(...), $categories));
        }

        $this->applyOptionalScalar($manifest, 'iarc_rating_id', $manifestConfig['iarc_rating_id'] ?? null);

        if (($manifestConfig['prefer_related_applications'] ?? false) === true) {
            $manifest['prefer_related_applications'] = true;
        }

        $related = $this->normalizeRelatedApplications($manifestConfig['related_applications'] ?? []);
        if ($related !== []) {
            $manifest['related_applications'] = $related;
        }

        $scopeExtensions = $this->normalizeScopeExtensions($manifestConfig['scope_extensions'] ?? []);
        if ($scopeExtensions !== []) {
            $manifest['scope_extensions'] = $scopeExtensions;
        }

        $launchHandler = $this->normalizeLaunchHandler($manifestConfig['launch_handler'] ?? []);
        if ($launchHandler !== []) {
            $manifest['launch_handler'] = $launchHandler;
        }

        $protocolHandlers = $this->normalizeProtocolHandlers($manifestConfig['protocol_handlers'] ?? []);
        if ($protocolHandlers !== []) {
            $manifest['protocol_handlers'] = $protocolHandlers;
        }

        $fileHandlers = $this->normalizeFileHandlers($manifestConfig['file_handlers'] ?? []);
        if ($fileHandlers !== []) {
            $manifest['file_handlers'] = $fileHandlers;
        }

        $shareTarget = $this->normalizeShareTarget($manifestConfig['share_target'] ?? []);
        if ($shareTarget !== []) {
            $manifest['share_target'] = $shareTarget;
        }

        $edgeSidePanel = $this->normalizeEdgeSidePanel($manifestConfig['edge_side_panel'] ?? []);
        if ($edgeSidePanel !== []) {
            $manifest['edge_side_panel'] = $edgeSidePanel;
        }

        return $manifest;
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function applyOptionalScalar(array &$manifest, string $key, mixed $value): void
    {
        if (!is_string($value) || $value === '') {
            return;
        }

        $manifest[$key] = $value;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $list = [];
        foreach ($values as $value) {
            if (is_string($value) && $value !== '') {
                $list[] = $value;
            }
        }

        return $list;
    }

    /**
     * @return list<array<string, string>>
     */
    private function normalizeIcons(mixed $icons): array
    {
        if (!is_array($icons) || !array_is_list($icons)) {
            return [];
        }

        $normalized = [];
        foreach ($icons as $icon) {
            if (!is_array($icon)) {
                continue;
            }

            $src = (string) ($icon['src'] ?? '');
            if ($src === '') {
                continue;
            }

            $normalized[] = [
                'src'     => $src,
                'sizes'   => (string) ($icon['sizes'] ?? '192x192'),
                'type'    => (string) ($icon['type'] ?? 'image/png'),
                'purpose' => (string) ($icon['purpose'] ?? 'any'),
            ];
        }

        return $normalized;
    }

    /**
     * @return list<array<string, string>>
     */
    private function normalizeScreenshots(mixed $screenshots): array
    {
        if (!is_array($screenshots) || !array_is_list($screenshots)) {
            return [];
        }

        $normalized = [];
        foreach ($screenshots as $shot) {
            if (!is_array($shot)) {
                continue;
            }

            $src = (string) ($shot['src'] ?? '');
            if ($src === '') {
                continue;
            }

            $entry = [
                'src'   => $src,
                'sizes' => (string) ($shot['sizes'] ?? '1280x720'),
                'type'  => (string) ($shot['type'] ?? 'image/png'),
            ];

            if (is_string($shot['label'] ?? null) && $shot['label'] !== '') {
                $entry['label'] = $shot['label'];
            }

            if (is_string($shot['form_factor'] ?? null) && $shot['form_factor'] !== '') {
                $entry['form_factor'] = $shot['form_factor'];
            }

            $normalized[] = $entry;
        }

        return $normalized;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeShortcuts(mixed $shortcuts): array
    {
        if (!is_array($shortcuts) || !array_is_list($shortcuts)) {
            return [];
        }

        $normalized = [];
        foreach ($shortcuts as $shortcut) {
            if (!is_array($shortcut)) {
                continue;
            }

            $name = (string) ($shortcut['name'] ?? '');
            $url  = (string) ($shortcut['url'] ?? '');
            if ($name === '' || $url === '') {
                continue;
            }

            $entry = ['name' => $name, 'url' => $url];
            if (is_string($shortcut['short_name'] ?? null) && $shortcut['short_name'] !== '') {
                $entry['short_name'] = $shortcut['short_name'];
            }
            if (is_string($shortcut['description'] ?? null) && $shortcut['description'] !== '') {
                $entry['description'] = $shortcut['description'];
            }

            $shortcutIcons = $this->normalizeIcons($shortcut['icons'] ?? []);
            if ($shortcutIcons !== []) {
                $entry['icons'] = $shortcutIcons;
            }

            $normalized[] = $entry;
        }

        return $normalized;
    }

    /**
     * @return list<array<string, string>>
     */
    private function normalizeRelatedApplications(mixed $apps): array
    {
        if (!is_array($apps) || !array_is_list($apps)) {
            return [];
        }

        $normalized = [];
        foreach ($apps as $app) {
            if (!is_array($app)) {
                continue;
            }

            $platform = (string) ($app['platform'] ?? '');
            if ($platform === '') {
                continue;
            }

            $entry = ['platform' => $platform];
            if (is_string($app['url'] ?? null) && $app['url'] !== '') {
                $entry['url'] = $app['url'];
            }
            if (is_string($app['id'] ?? null) && $app['id'] !== '') {
                $entry['id'] = $app['id'];
            }

            $normalized[] = $entry;
        }

        return $normalized;
    }

    /**
     * @return list<array<string, string>>
     */
    private function normalizeScopeExtensions(mixed $extensions): array
    {
        if (!is_array($extensions) || !array_is_list($extensions)) {
            return [];
        }

        $normalized = [];
        foreach ($extensions as $extension) {
            if (!is_array($extension)) {
                continue;
            }

            $origin = (string) ($extension['origin'] ?? '');
            if ($origin === '') {
                continue;
            }

            $normalized[] = [
                'origin' => $origin,
                'type'   => (string) ($extension['type'] ?? 'origin'),
            ];
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $launchHandler
     *
     * @return array<string, string>
     */
    private function normalizeLaunchHandler(array $launchHandler): array
    {
        $clientMode = (string) ($launchHandler['client_mode'] ?? '');
        if ($clientMode === '' || $clientMode === 'auto') {
            return [];
        }

        return ['client_mode' => $clientMode];
    }

    /**
     * @return list<array<string, string>>
     */
    private function normalizeProtocolHandlers(mixed $handlers): array
    {
        if (!is_array($handlers) || !array_is_list($handlers)) {
            return [];
        }

        $normalized = [];
        foreach ($handlers as $handler) {
            if (!is_array($handler)) {
                continue;
            }

            $protocol = (string) ($handler['protocol'] ?? '');
            $url      = (string) ($handler['url'] ?? '');
            if ($protocol === '' || $url === '') {
                continue;
            }

            $normalized[] = ['protocol' => $protocol, 'url' => $url];
        }

        return $normalized;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeFileHandlers(mixed $handlers): array
    {
        if (!is_array($handlers) || !array_is_list($handlers)) {
            return [];
        }

        $normalized = [];
        foreach ($handlers as $handler) {
            if (!is_array($handler)) {
                continue;
            }

            $action = (string) ($handler['action'] ?? '');
            if ($action === '') {
                continue;
            }

            $acceptMap = $this->normalizeAcceptMap($handler['accept_map'] ?? []);
            if ($acceptMap === []) {
                continue;
            }

            $entry = ['action' => $action, 'accept' => $acceptMap];
            $icons = $this->normalizeIcons($handler['icons'] ?? []);
            if ($icons !== []) {
                $entry['icons'] = $icons;
            }

            $normalized[] = $entry;
        }

        return $normalized;
    }

    /**
     * @return array<string, list<string>>
     */
    private function normalizeAcceptMap(mixed $acceptMap): array
    {
        if (!is_array($acceptMap)) {
            return [];
        }

        $normalized = [];
        foreach ($acceptMap as $mime => $extensions) {
            if (!is_string($mime) || $mime === '' || !is_array($extensions)) {
                continue;
            }

            $extList = [];
            foreach ($extensions as $ext) {
                if (is_string($ext) && $ext !== '') {
                    $extList[] = $ext;
                }
            }

            if ($extList !== []) {
                $normalized[$mime] = $extList;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $shareTarget
     *
     * @return array<string, mixed>
     */
    private function normalizeShareTarget(array $shareTarget): array
    {
        $action = (string) ($shareTarget['action'] ?? '');
        if ($action === '') {
            return [];
        }

        $normalized = ['action' => $action];
        $method     = (string) ($shareTarget['method'] ?? 'GET');
        if ($method !== 'GET') {
            $normalized['method'] = $method;
        }

        $enctype = (string) ($shareTarget['enctype'] ?? '');
        if ($enctype !== '') {
            $normalized['enctype'] = $enctype;
        }

        $params = $shareTarget['params'] ?? [];
        if (is_array($params)) {
            $paramEntries = [];
            foreach (['title', 'text', 'url', 'files'] as $key) {
                if (is_string($params[$key] ?? null) && $params[$key] !== '') {
                    $paramEntries[$key] = $params[$key];
                }
            }

            if ($paramEntries !== []) {
                $normalized['params'] = $paramEntries;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $edgeSidePanel
     *
     * @return array<string, int>
     */
    private function normalizeEdgeSidePanel(array $edgeSidePanel): array
    {
        $width = $edgeSidePanel['preferred_width'] ?? null;
        if (!is_int($width) && !is_numeric($width)) {
            return [];
        }

        return ['preferred_width' => (int) $width];
    }
}
