<?php

namespace EWZ\SymfonyAdminBundle\Util;

use Doctrine\Inflector\Inflector as DoctrineInflector;
use Doctrine\Inflector\InflectorFactory;

final class Inflector
{
    /** @var DoctrineInflector|null */
    public static $inflector;

    /**
     * @return DoctrineInflector
     */
    public static function getInstance(): DoctrineInflector
    {
        if (null === static::$inflector) {
            static::$inflector = InflectorFactory::create()->build();
        }

        return static::$inflector;
    }
}
