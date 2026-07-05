<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\DependencyInjection\Configuration;

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
                ->variableNode('deny_cache_patterns')->defaultValue([])->end()
                ->scalarNode('offline_url')->defaultNull()->end()
                ->integerNode('runtime_cache_max_entries')->defaultValue(0)->min(0)->end()
            ->end();
    }
}
