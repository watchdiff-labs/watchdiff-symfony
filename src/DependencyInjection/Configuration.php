<?php

declare(strict_types=1);

namespace WatchDiff\Symfony\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('watch_diff');

        // TreeBuilder::getRootNode() is statically typed to the generic
        // NodeDefinition in some symfony/config versions (it's always an
        // ArrayNodeDefinition at runtime for a tree built with the default
        // 'array' root type) - without this hint, PHPStan can't see
        // children() below on versions where the return type isn't narrowed.
        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('config_path')->defaultValue('%kernel.project_dir%/watchdiff.config.json')->end()
                ->arrayNode('store')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('driver')->values(['json', 'sqlite'])->defaultValue('json')->end()
                        ->scalarNode('json_directory')->defaultValue('%kernel.project_dir%/var/watchdiff')->end()
                        ->scalarNode('sqlite_path')->defaultValue('%kernel.project_dir%/var/watchdiff.db')->end()
                        // Database-table watchers (DbWatcher) intentionally never
                        // share storage with the URL/file/cert watchers above -
                        // `watchdiff:clean` deletes any stored key that isn't a
                        // configured URL/file/cert watcher, so a shared directory
                        // would make DbWatcher's own watchers look like orphans.
                        ->scalarNode('db_json_directory')->defaultValue('%kernel.project_dir%/var/watchdiff-db')->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
