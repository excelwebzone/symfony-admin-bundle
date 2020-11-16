<?php

namespace EWZ\SymfonyAdminBundle;

use EWZ\SymfonyAdminBundle\DependencyInjection\Compiler\InjectRememberMeServicesPass;
use EWZ\SymfonyAdminBundle\DependencyInjection\Compiler\InjectUserCheckerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class SymfonyAdminBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new InjectUserCheckerPass());
        $container->addCompilerPass(new InjectRememberMeServicesPass());
    }
}
