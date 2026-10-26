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
            ->end();

        return $treeBuilder;
    }
}
