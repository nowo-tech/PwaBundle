<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\DependencyInjection;

use Nowo\PwaBundle\DependencyInjection\Configuration\ManifestNodeDefinition;
use Nowo\PwaBundle\DependencyInjection\Configuration\MetaNodeDefinition;
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

        $root
            ->children()
                ->arrayNode('install_prompt')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->scalarNode('dismiss_key')->defaultValue('nowo_pwa_install_dismissed')->end()
                        ->integerNode('dismiss_days')->defaultValue(7)->min(0)->end()
                        ->enumNode('position')->values(['bottom', 'top'])->defaultValue('bottom')->end()
                        ->scalarNode('css_class')->defaultValue('nowo-pwa-install')->end()
                        ->integerNode('delay_ms')->defaultValue(0)->min(0)->end()
                        ->enumNode('visibility')->values(['all', 'mobile', 'desktop'])->defaultValue('all')->end()
                    ->end()
                ->end()
                ->arrayNode('install_links')
                    ->info('Toggle install / uninstall links (one visible at a time).')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->scalarNode('css_class')->defaultValue('nowo-pwa-install-links')->end()
                        ->enumNode('visibility')->values(['all', 'mobile', 'desktop'])->defaultValue('all')->end()
                    ->end()
                ->end()
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
                    ->end()
                ->end()
                ->arrayNode('route_targeting')
                    ->info('Limit where PWA head tags and client script are injected.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('mode')->values(['all', 'only', 'except'])->defaultValue('all')->end()
                        ->variableNode('routes')->defaultValue([])->end()
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
