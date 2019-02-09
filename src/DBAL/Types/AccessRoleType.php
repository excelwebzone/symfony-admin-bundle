<?php

namespace EWZ\SymfonyAdminBundle\DBAL\Types;

use Fresh\DoctrineEnumBundle\DBAL\Types\AbstractEnumType;

final class AccessRoleType extends AbstractEnumType
{
    const CREATE = 'create';
    const EDIT = 'edit';
    const DELETE = 'delete';

    protected static $choices = [
        self::CREATE => 'Create',
        self::EDIT => 'Edit',
        self::DELETE => 'Delete',
    ];
}
