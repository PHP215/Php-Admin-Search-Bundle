<?php

namespace Sonata\AdminSearchBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('sonata_admin_search');

        $rootNode
            ->children()
                ->arrayNode('admin_finder_services')
                  ->useAttributeAsKey('admin_id')
                  ->prototype('array')
                      ->children()
                          ->scalarNode('finder')->isRequired()->end()
                      ->end()
                  ->end()
            ->end();

        return $treeBuilder;
    }
}

