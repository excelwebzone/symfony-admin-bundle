<?php

namespace EWZ\SymfonyAdminBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('symfony_admin');

        $rootNode
            ->children()
                ->scalarNode('upload_url')->defaultValue('uploads')->end()
                ->arrayNode('services')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('cron_schedule_repository')->defaultValue('symfony_admin.cron_schedule_repository.default')->end()
                        ->scalarNode('user_repository')->defaultValue('symfony_admin.user_repository.default')->end()
                        ->scalarNode('report_repository')->defaultValue('symfony_admin.report_repository.default')->end()
                        ->scalarNode('file_uploader')->defaultValue('symfony_admin.file_uploader.default')->end()
                    ->end()
                ->end()
                ->arrayNode('mime_types')
                    ->info('mime-types configuration')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('extensions')->defaultValue('PNG, GIF, JPG, PDF, CSV, XLS, or XLSX')->end()
                        ->scalarNode('types')
                            ->defaultValue([
                                'image/png',
                                'image/jpeg',
                                'image/jpg',
                                'image/gif',
                                'application/pdf',
                                'text/plain',
                                'text/csv',
                                'application/vnd.ms-excel',
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            ])
                        ->end()

                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
