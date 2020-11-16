<?php

namespace EWZ\SymfonyAdminBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Injects firewall's UserChecker into LoginManager.
 */
class InjectUserCheckerPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $firewallName = $container->getParameter('symfony_admin.firewall_name');
        $loginManager = $container->findDefinition('symfony_admin.security.login_manager');

        if ($container->has(sprintf('security.user_checker.%s', $firewallName))) {
            $loginManager->replaceArgument(1, new Reference(sprintf('security.user_checker.%s', $firewallName)));
        }
    }
}
