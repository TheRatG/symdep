<?php
namespace TheRat\SymDep\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{

    /**
     * Generates the configuration tree builder.
     *
     * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('symdep');

        /** @var ArrayNodeDefinition $rootNode */
        $rootNode
            ->children()
                ->booleanNode('switch_db')->defaultFalse()->end()
                ->booleanNode('copy_db_data')->defaultFalse()->end()
            ->end();

        $rootNode
            ->children()
                ->arrayNode('alter_increment_map')
                    ->prototype('array')
                        ->children()
                            ->arrayNode('test')
                                ->children()
                                    ->scalarNode('start')->isRequired()->end()
                                    ->scalarNode('step')->isRequired()->end()
                                ->end()
                            ->end()
                                ->arrayNode('dev')
                                    ->children()
                                        ->scalarNode('start')->isRequired()->end()
                                        ->scalarNode('step')->isRequired()->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
