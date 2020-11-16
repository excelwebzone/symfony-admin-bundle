<?php

namespace EWZ\SymfonyAdminBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Inject RememberMeServices into LoginManager.
 */
class InjectRememberMeServicesPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $firewallName = $container->getParameter('symfony_admin.firewall_name');
        $loginManager = $container->findDefinition('symfony_admin.security.login_manager');

        if ($container->hasDefinition(sprintf('security.authentication.rememberme.services.persistent.%s', $firewallName))) {
            $loginManager->replaceArgument(4, new Reference(sprintf('security.authentication.rememberme.services.persistent.%s', $firewallName)));
        } elseif ($container->hasDefinition(sprintf('security.authentication.rememberme.services.simplehash.%s', $firewallName))) {
            $loginManager->replaceArgument(4, new Reference(sprintf('security.authentication.rememberme.services.simplehash.%s', $firewallName)));
        }
    }
}
