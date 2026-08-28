<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\DependencyInjection;

use Nowo\PwaBundle\DependencyInjection\Configuration\ManifestNodeDefinition;
use Nowo\PwaBundle\DependencyInjection\Configuration\MetaNodeDefinition;
use Nowo\PwaBundle\DependencyInjection\Configuration\RouteTargetingNodeDefinition;
use Nowo\PwaBundle\DependencyInjection\Configuration\ServiceWorkerNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Configuration tree for PwaBundle.
 */
final class Configuration implements ConfigurationInterface
{
    public const ALIAS = 'nowo_pwa';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(self::ALIAS);
        $root        = $treeBuilder->getRootNode();

        $root
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
                ->scalarNode('button_class')->defaultValue('')->end()
                ->scalarNode('dismiss_button_class')->defaultNull()->end()
                ->scalarNode('never_button_class')->defaultNull()->end()
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
                    ->end()
                ->end()
                ->arrayNode('templates')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('head')->defaultValue('@NowoPwaBundle/pwa/head.html.twig')->cannotBeEmpty()->end()
                        ->scalarNode('install_prompt')->defaultValue('@NowoPwaBundle/pwa/install_prompt.html.twig')->cannotBeEmpty()->end()
                        ->scalarNode('install_links')->defaultValue('@NowoPwaBundle/pwa/install_links.html.twig')->cannotBeEmpty()->end()
                        ->scalarNode('offline')->defaultValue('@NowoPwaBundle/pwa/offline.html.twig')->cannotBeEmpty()->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
