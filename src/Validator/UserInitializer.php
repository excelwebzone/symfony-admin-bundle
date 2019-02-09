<?php

namespace EWZ\SymfonyAdminBundle\Validator;

use EWZ\SymfonyAdminBundle\Modal\User;
use Symfony\Component\Validator\ObjectInitializerInterface;

/**
 * Automatically updates the canonical fields before validation.
 */
class UserInitializer implements ObjectInitializerInterface
{
    /**
     * {@inheritdoc}
     */
    public function initialize($object)
    {
        if ($object instanceof User) {
            $object->updateCanonicalFields();
        }
    }
}
