<?php

namespace EWZ\SymfonyAdminBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

final class SymfonyAdminExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader($container, new FileLocator(\dirname(__DIR__).'/Resources/config'));
        $loader->load('services.yaml');

        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('symfony_admin.upload_url', $config['upload_url']);

        $container->setAlias('symfony_admin.cron_schedule_repository', new Alias($config['services']['cron_schedule_repository'], true));
        $container->setAlias('symfony_admin.user_repository', new Alias($config['services']['user_repository'], true));
        $container->setAlias('symfony_admin.report_repository', new Alias($config['services']['report_repository'], true));
        $container->setAlias('symfony_admin.file_uploader', new Alias($config['services']['file_uploader'], true));

        $container->setParameter('symfony_admin.max_filesize', $config['max_filesize']);

        $container->setParameter('symfony_admin.mime_types.extensions', $config['mime_types']['extensions']);
        $container->setParameter('symfony_admin.mime_types.types', $config['mime_types']['types']);
    }
}
