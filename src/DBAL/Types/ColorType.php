<?php

namespace EWZ\SymfonyAdminBundle\DBAL\Types;

use Fresh\DoctrineEnumBundle\DBAL\Types\AbstractEnumType;

final class ColorType extends AbstractEnumType
{
    const GREEN = '006400';
    const GOLD = 'cdad00';
    const YELLOW = 'eeee00';
    const ORANGE = 'ffa500';
    const RED = 'ff0000';
    const PINK = 'ffc0cb';
    const BLUE = '0000ff';
    const PURPLE = 'a020f0';
    const BLACK = '000000';
    const AQUA = '00ffff';
    const LIME = '00ff00';
    const GREY = '999999';
    const BROWN = '8b4513';

    protected static $choices = [
        self::GREEN => 'Green',
        self::GOLD => 'Gold',
        self::YELLOW => 'Yellow',
        self::ORANGE => 'Orange',
        self::RED => 'Red',
        self::PINK => 'Pink',
        self::BLUE => 'Blue',
        self::PURPLE => 'Purple',
        self::BLACK => 'Black',
        self::AQUA => 'Aqua',
        self::LIME => 'Lime',
        self::GREY => 'Grey',
        self::BROWN => 'Brown',
    ];
}
