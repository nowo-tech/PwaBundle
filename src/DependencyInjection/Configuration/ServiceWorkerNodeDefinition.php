<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\DependencyInjection\Configuration;

use Nowo\PwaBundle\Service\ServiceWorkerCacheDefaults;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

final class ServiceWorkerNodeDefinition
{
    public static function configure(ArrayNodeDefinition $node): void
    {
        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('enabled')->defaultTrue()->end()
                ->scalarNode('scope')->defaultValue('/')->cannotBeEmpty()->end()
                ->booleanNode('skip_waiting')->defaultTrue()->end()
                ->booleanNode('clients_claim')->defaultTrue()->end()
                ->booleanNode('navigation_preload')->defaultFalse()->end()
                ->scalarNode('cache_version')->defaultValue('v1')->cannotBeEmpty()->end()
                ->scalarNode('cache_name_prefix')->defaultValue('nowo-pwa')->cannotBeEmpty()->end()
                ->enumNode('strategy')
                    ->values(['network-first', 'cache-first', 'stale-while-revalidate'])
                    ->defaultValue('network-first')
                ->end()
                ->variableNode('precache_urls')->defaultValue(['/'])->end()
                ->variableNode('runtime_cache_patterns')->defaultValue([])->end()
                ->variableNode('deny_cache_patterns')
                    ->defaultValue(ServiceWorkerCacheDefaults::denyCachePatterns())
                    ->info('Substring patterns never cached. Defaults exclude auth/admin/API/profiler paths. An explicit empty list disables the defaults.')
                ->end()
                ->scalarNode('offline_url')->defaultNull()->end()
                ->integerNode('runtime_cache_max_entries')->defaultValue(0)->min(0)->end()
                ->booleanNode('web_push')
                    ->defaultFalse()
                    ->info('When true, append the kit Web Push / notificationclick handlers (JSON: title, body, url, …). Prefer this over a host SW rewrite.')
                ->end()
                ->arrayNode('web_push_defaults')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('title')->defaultValue('Notification')->end()
                        ->scalarNode('icon')->defaultValue('/icons/icon-192.png')->end()
                        ->scalarNode('badge')->defaultValue('/icons/icon-192.png')->end()
                        ->scalarNode('url')->defaultValue('/')->end()
                        ->scalarNode('tag')->defaultValue('nowo-pwa')->end()
                    ->end()
                ->end()
                ->scalarNode('append_script')
                    ->defaultNull()
                    ->info('Optional raw JavaScript appended after the kit web_push handlers (if enabled). Prefer web_push: true for standard push UI.')
                ->end()
            ->end();
    }
}
