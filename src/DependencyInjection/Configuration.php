<?php

namespace Kerrialnewham\Autocomplete\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('autocomplete');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('theme')
                    ->defaultValue('default')
                    ->info('The default theme to use for autocomplete widgets')
                ->end()
                ->arrayNode('allowed_themes')
                    ->scalarPrototype()->end()
                    ->defaultValue(['default', 'dark', 'cards', 'bootstrap-5'])
                    ->info('List of allowed theme names')
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
