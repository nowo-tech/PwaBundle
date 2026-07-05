<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\DependencyInjection\Configuration;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

/**
 * HTML head meta tags for PWA (Apple, Microsoft, theme, viewport).
 */
final class MetaNodeDefinition
{
    public static function configure(ArrayNodeDefinition $node): void
    {
        $node
            ->info('Additional HTML head tags (Apple, Microsoft, theme, viewport).')
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('mobile_web_app_capable')->defaultTrue()->end()
                ->booleanNode('apple_mobile_web_app_capable')->defaultTrue()->end()
                ->scalarNode('apple_status_bar_style')
                    ->defaultValue('default')
                    ->validate()
                        ->ifNotInArray(['default', 'black', 'black-translucent'])
                        ->thenInvalid('Invalid apple_status_bar_style %s')
                    ->end()
                ->end()
                ->scalarNode('apple_mobile_web_app_title')->defaultNull()->end()
                ->enumNode('viewport_fit')->values(['auto', 'cover', 'contain'])->defaultNull()->end()
                ->scalarNode('theme_color_light')->defaultNull()->end()
                ->scalarNode('theme_color_dark')->defaultNull()->end()
                ->scalarNode('color_scheme')->defaultNull()->end()
                ->scalarNode('msapplication_tile_color')->defaultNull()->end()
                ->scalarNode('msapplication_tile_image')->defaultNull()->end()
                ->scalarNode('msapplication_config')->defaultNull()->end()
                ->scalarNode('referrer')->defaultNull()->end()
                ->arrayNode('format_detection')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('telephone')->defaultNull()->end()
                        ->booleanNode('email')->defaultNull()->end()
                        ->booleanNode('address')->defaultNull()->end()
                    ->end()
                ->end()
                ->arrayNode('apple_touch_icons')
                    ->defaultValue([])
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('href')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('sizes')->defaultValue('180x180')->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('apple_startup_images')
                    ->defaultValue([])
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('href')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('media')->defaultNull()->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('mask_icon')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('href')->defaultNull()->end()
                        ->scalarNode('color')->defaultNull()->end()
                    ->end()
                ->end()
                ->arrayNode('extra_link_tags')
                    ->defaultValue([])
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('rel')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('href')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('type')->defaultNull()->end()
                            ->scalarNode('sizes')->defaultNull()->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }
}
