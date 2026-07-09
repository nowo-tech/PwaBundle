<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\DependencyInjection\Configuration;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

/**
 * Shared route/path targeting node for PWA UI components.
 */
final class RouteTargetingNodeDefinition
{
    public static function configure(ArrayNodeDefinition $node): void
    {
        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->enumNode('match_by')
                    ->info('Match routes by Symfony route name or request path pattern.')
                    ->values(['name', 'path'])
                    ->defaultValue('name')
                ->end()
                ->enumNode('mode')
                    ->info('all: every page; only: listed routes/paths; except: all except listed.')
                    ->values(['all', 'only', 'except'])
                    ->defaultValue('all')
                ->end()
                ->variableNode('routes')
                    ->info('Route names or path patterns (exact, prefix with trailing *, or /regex/).')
                    ->defaultValue([])
                ->end()
            ->end();
    }
}
