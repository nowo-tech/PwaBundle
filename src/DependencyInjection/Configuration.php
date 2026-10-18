<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\DependencyInjection;

use Nowo\PwaBundle\DependencyInjection\Configuration\ManifestNodeDefinition;
use Nowo\PwaBundle\DependencyInjection\Configuration\MetaNodeDefinition;
use Nowo\PwaBundle\DependencyInjection\Configuration\RouteTargetingNodeDefinition;
use Nowo\PwaBundle\DependencyInjection\Configuration\ServiceWorkerNodeDefinition;
use Nowo\PwaBundle\DependencyInjection\Configuration\WebPushNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

use function array_key_exists;
use function is_array;
use function is_string;
use function ltrim;
use function parse_url;
use function rtrim;
use function sprintf;
use function str_ends_with;
use function str_starts_with;

use const PHP_URL_PATH;

/**
 * Configuration tree for PwaBundle.
 */
final class Configuration implements ConfigurationInterface
{
    public const ALIAS = 'nowo_pwa';

    /** Package and version of the deprecations introduced with the UiKit page contract (REQ-UI-003). */
    public const DEPRECATION_PACKAGE = 'nowo-tech/pwa-bundle';

    public const DEPRECATION_VERSION = '1.10';

    /** Templates of the bundle (web_ui.templates.<name>, 1.x templates.<name>). */
    public const TEMPLATE_DEFAULTS = [
        'head'           => '@NowoPwaBundle/pwa/head.html.twig',
        'install_prompt' => '@NowoPwaBundle/pwa/install_prompt.html.twig',
        'install_links'  => '@NowoPwaBundle/pwa/install_links.html.twig',
        'offline'        => '@NowoPwaBundle/pwa/offline.html.twig',
    ];

    /** Default layout of the offline page (self-contained: inline CSS, no stylesheet or script request). */
    public const OFFLINE_LAYOUT = '@NowoUiKit/layout/status.html.twig';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(self::ALIAS);
        $root        = $treeBuilder->getRootNode();

        $root
            ->beforeNormalization()
                ->always(static function (mixed $v): mixed {
                    if (!is_array($v) || !is_array($v['templates'] ?? null)) {
                        return $v;
                    }
                    // REQ-UI-003 canonical keys: templates.<name> → web_ui.templates.<name> (the canonical key wins).
                    $webUi     = is_array($v['web_ui'] ?? null) ? $v['web_ui'] : [];
                    $templates = is_array($webUi['templates'] ?? null) ? $webUi['templates'] : [];
                    foreach ($v['templates'] as $name => $template) {
                        if (is_string($name) && !array_key_exists($name, $templates)) {
                            $templates[$name] = $template;
                        }
                    }
                    $webUi['templates'] = $templates;
                    $v['web_ui']        = $webUi;

                    return $v;
                })
            ->end()
            ->children()
                ->booleanNode('enabled')
                    ->info('Master switch for PWA features (manifest, service worker, head tags).')
                    ->defaultTrue()
                ->end()
                ->scalarNode('route_prefix')
                    ->info('Optional prefix prepended to all bundle PWA routes.')
                    ->defaultValue('')
                ->end()
            ->end();

        ManifestNodeDefinition::configure($root->children()->arrayNode('manifest'));
        MetaNodeDefinition::configure($root->children()->arrayNode('meta'));
        ServiceWorkerNodeDefinition::configure($root->children()->arrayNode('service_worker'));
        WebPushNodeDefinition::configure($root->children()->arrayNode('web_push'));

        $installPromptNode = $root->children()->arrayNode('install_prompt');
        $installPromptNode
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('enabled')->defaultTrue()->end()
                ->enumNode('display')
                    ->info('banner: fixed bar; flash: inline alert; modal: centered dialog.')
                    ->values(['banner', 'flash', 'modal'])
                    ->defaultValue('banner')
                ->end()
                ->scalarNode('dismiss_key')->defaultValue('nowo_pwa_install_dismissed')->end()
                ->integerNode('dismiss_days')->defaultValue(7)->min(0)->end()
                ->scalarNode('never_dismiss_key')->defaultValue('nowo_pwa_install_never')->end()
                ->booleanNode('show_never_option')->defaultTrue()->end()
                ->enumNode('position')->values(['bottom', 'top'])->defaultValue('bottom')->end()
                ->scalarNode('css_class')->defaultValue('nowo-pwa-install')->end()
                ->scalarNode('mark_asset')->defaultNull()->end()
                ->scalarNode('title')->defaultNull()->end()
                ->scalarNode('eyebrow')->defaultNull()->end()
                ->scalarNode('button_class')
                    ->info('Deprecated: replaces the classes of the install button (theme slot btn_primary).')
                    ->defaultValue('')
                    ->setDeprecated(self::DEPRECATION_PACKAGE, self::DEPRECATION_VERSION, 'The "%path%.%node%" option is deprecated, use "nowo_ui_kit.theme.bundles.NowoPwaBundle.classes.btn_primary" (UiKit theme slot) instead.')
                ->end()
                ->scalarNode('dismiss_button_class')
                    ->info('Deprecated: replaces the classes of the "Not now" button (theme slot btn_secondary).')
                    ->defaultNull()
                    ->setDeprecated(self::DEPRECATION_PACKAGE, self::DEPRECATION_VERSION, 'The "%path%.%node%" option is deprecated, use "nowo_ui_kit.theme.bundles.NowoPwaBundle.classes.btn_secondary" (UiKit theme slot) instead.')
                ->end()
                ->scalarNode('never_button_class')
                    ->info('Deprecated: replaces the classes of the "Don\'t ask again" button (theme slot btn_link).')
                    ->defaultNull()
                    ->setDeprecated(self::DEPRECATION_PACKAGE, self::DEPRECATION_VERSION, 'The "%path%.%node%" option is deprecated, use "nowo_ui_kit.theme.bundles.NowoPwaBundle.classes.btn_link" (UiKit theme slot) instead.')
                ->end()
                ->integerNode('delay_ms')->defaultValue(0)->min(0)->end()
                ->enumNode('visibility')->values(['all', 'mobile', 'desktop'])->defaultValue('all')->end()
            ->end();
        RouteTargetingNodeDefinition::configure($installPromptNode->children()->arrayNode('route_targeting'));

        $installLinksNode = $root->children()->arrayNode('install_links');
        $installLinksNode
            ->info('Toggle install / uninstall links (one visible at a time).')
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('enabled')->defaultTrue()->end()
                ->scalarNode('css_class')->defaultValue('nowo-pwa-install-links')->end()
                ->enumNode('visibility')->values(['all', 'mobile', 'desktop'])->defaultValue('all')->end()
            ->end();
        RouteTargetingNodeDefinition::configure($installLinksNode->children()->arrayNode('route_targeting'));

        $globalRouteTargetingNode = $root->children()->arrayNode('route_targeting');
        $globalRouteTargetingNode->info('Limit where PWA head tags and client script are injected.');
        RouteTargetingNodeDefinition::configure($globalRouteTargetingNode);

        $root
            ->children()
                ->arrayNode('client')
                    ->info('Browser client script behaviour (pwa.js).')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('register_on_load')->defaultTrue()->end()
                        ->booleanNode('check_updates_on_visibility')->defaultTrue()->end()
                        ->booleanNode('reload_on_update')->defaultFalse()->end()
                    ->end()
                ->end()
                ->arrayNode('http')
                    ->info('HTTP cache headers for manifest and service worker responses.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('manifest_cache_max_age')->defaultValue(3600)->min(0)->end()
                        ->integerNode('service_worker_cache_max_age')->defaultValue(0)->min(0)->end()
                        ->booleanNode('manifest_public_cache')->defaultTrue()->end()
                        ->booleanNode('strip_set_cookie_on_bootstrap')
                            ->defaultTrue()
                            ->info('Remove Set-Cookie from manifest and service worker responses so anonymous fetches cannot overwrite the session cookie.')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('routes')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('manifest')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('path')->defaultValue('/manifest.webmanifest')->cannotBeEmpty()->end()
                                ->scalarNode('name')->defaultValue('nowo_pwa_manifest')->cannotBeEmpty()->end()
                            ->end()
                        ->end()
                        ->arrayNode('service_worker')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('path')->defaultValue('/sw.js')->cannotBeEmpty()->end()
                                ->scalarNode('name')->defaultValue('nowo_pwa_service_worker')->cannotBeEmpty()->end()
                            ->end()
                        ->end()
                        ->arrayNode('offline')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('path')->defaultValue('/offline')->cannotBeEmpty()->end()
                                ->scalarNode('name')->defaultValue('nowo_pwa_offline')->cannotBeEmpty()->end()
                            ->end()
                        ->end()
                        ->arrayNode('push_test')
                            ->info('POST test-notification endpoint (registered only when web_push.test_notification.enabled).')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('path')->defaultValue('/pwa/push/test')->cannotBeEmpty()->end()
                                ->scalarNode('name')->defaultValue('nowo_pwa_push_test')->cannotBeEmpty()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('web_ui')
                    ->info('UiKit page contract (REQ-UI-003): offline page layout and per-template overrides.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('layout_template')
                            ->info('Layout of the offline page. Null (default): ' . self::OFFLINE_LAYOUT . ' (self-contained, works offline).')
                            ->defaultNull()
                        ->end()
                        ->arrayNode('templates')
                            ->info('Per-template overrides (a host template extending @!NowoPwaBundle/pwa/... and overriding a block).')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('head')->defaultValue(self::TEMPLATE_DEFAULTS['head'])->cannotBeEmpty()->end()
                                ->scalarNode('install_prompt')->defaultValue(self::TEMPLATE_DEFAULTS['install_prompt'])->cannotBeEmpty()->end()
                                ->scalarNode('install_links')->defaultValue(self::TEMPLATE_DEFAULTS['install_links'])->cannotBeEmpty()->end()
                                ->scalarNode('offline')->defaultValue(self::TEMPLATE_DEFAULTS['offline'])->cannotBeEmpty()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('templates')
                    ->info('Deprecated alias of web_ui.templates.')
                    ->children()
                        ->scalarNode('head')
                            ->setDeprecated(self::DEPRECATION_PACKAGE, self::DEPRECATION_VERSION, 'The "%path%.%node%" option is deprecated, use "nowo_pwa.web_ui.templates.head" instead.')
                        ->end()
                        ->scalarNode('install_prompt')
                            ->setDeprecated(self::DEPRECATION_PACKAGE, self::DEPRECATION_VERSION, 'The "%path%.%node%" option is deprecated, use "nowo_pwa.web_ui.templates.install_prompt" instead.')
                        ->end()
                        ->scalarNode('install_links')
                            ->setDeprecated(self::DEPRECATION_PACKAGE, self::DEPRECATION_VERSION, 'The "%path%.%node%" option is deprecated, use "nowo_pwa.web_ui.templates.install_links" instead.')
                        ->end()
                        ->scalarNode('offline')
                            ->setDeprecated(self::DEPRECATION_PACKAGE, self::DEPRECATION_VERSION, 'The "%path%.%node%" option is deprecated, use "nowo_pwa.web_ui.templates.offline" instead.')
                        ->end()
                    ->end()
                ->end()
            ->end();

        $root
            ->validate()
            ->always(static function (array $v): array {
                $swScope   = self::normalizePath((string) ($v['service_worker']['scope'] ?? '/'));
                $startRaw  = (string) ($v['manifest']['start_url'] ?? '/');
                $startPath = parse_url($startRaw, PHP_URL_PATH);
                $start     = self::normalizePath(is_string($startPath) && $startPath !== '' ? $startPath : $startRaw);

                if ($swScope !== '/' && !self::pathIsUnderScope($start, $swScope)) {
                    throw new InvalidConfigurationException(sprintf('nowo_pwa.manifest.start_url "%s" must be under service_worker.scope "%s".', $startRaw, $v['service_worker']['scope'] ?? '/'));
                }

                return $v;
            })
            ->end();

        return $treeBuilder;
    }

    private static function normalizePath(string $path): string
    {
        if ($path === '' || $path[0] !== '/') {
            $path = '/' . ltrim($path, '/');
        }
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        return $path === '' ? '/' : $path;
    }

    private static function pathIsUnderScope(string $path, string $scope): bool
    {
        // Callers skip the root scope "/", so $scope is a normalized non-root path here.
        $scope = rtrim($scope, '/');

        return $path === $scope || str_starts_with($path, $scope . '/');
    }
}
