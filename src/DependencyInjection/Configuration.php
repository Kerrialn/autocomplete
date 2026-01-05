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

        // No configuration options at this time
        // Theme is specified per form field, not globally
        $rootNode->children()->end();

        return $treeBuilder;
    }
}
