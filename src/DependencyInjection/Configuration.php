<?php

declare(strict_types=1);

namespace EilingIo\SyliusBatteryIncludedPlugin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('eiling_io_sylius_battery_included');

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('channels')
                    ->info('Channel code => name of the collection in the batteryincluded bundle configuration, e.g. FASHION_WEB: default. Without channels the first enabled channel is exported into the default collection.')
                    ->useAttributeAsKey('channel')
                    ->normalizeKeys(false)
                    ->scalarPrototype()->cannotBeEmpty()->end()
                    ->validate()
                        ->ifTrue(static fn (array $channels) => count($channels) !== count(array_unique($channels)))
                        ->thenInvalid('Every collection can only be assigned to one channel, got %s.')
                    ->end()
                ->end()
                ->arrayNode('presets')
                    ->info('Collection name => preset ids of the sliders, e.g. default: {homepage: <id>, not_found: <id>}. A slider without preset is not shown.')
                    ->useAttributeAsKey('collection')
                    ->normalizeKeys(false)
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('homepage')->info('Slider on the homepage')->defaultNull()->end()
                            ->scalarNode('not_found')->info('Slider on the 404 page and the search without hits')->defaultNull()->end()
                        ->end()
                    ->end()
                ->end()
                ->booleanNode('cms_pages')
                    ->info('Exports the pages of the Sylius CMS plugin and shows them in the search, only if the CMS plugin is installed and enabled')
                    ->defaultTrue()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
