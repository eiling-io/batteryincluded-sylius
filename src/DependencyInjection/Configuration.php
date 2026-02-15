<?php

declare(strict_types=1);

namespace EilingIo\SyliusBatteryIncludedPlugin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    /**
     * @psalm-suppress UnusedVariable
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('eiling_io_sylius_battery_included');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
            ->stringNode('collection')->defaultNull()->end()
            ->scalarNode('api_key')->defaultNull()->end()
            ->end();

        return $treeBuilder;
    }
}
