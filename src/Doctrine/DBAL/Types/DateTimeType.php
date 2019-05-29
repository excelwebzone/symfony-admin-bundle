<?php

namespace EWZ\SymfonyAdminBundle\Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\DateTimeType as BaseDateTimeType;
use EWZ\SymfonyAdminBundle\Util\DateTimeKernel;

class DateTimeType extends BaseDateTimeType
{
    /**
     * {@inheritdoc}
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        if ($value instanceof \DateTime) {
            if ($timezone = DateTimeKernel::getTimeZoneDatabase()) {
                $value->setTimezone($timezone);
            }
        }

        return parent::convertToDatabaseValue($value, $platform);
    }

    /**
     * {@inheritdoc}
     */
    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        if (null === $value || $value instanceof \DateTime) {
            return $value;
        }

        if ($converted = \DateTime::createFromFormat(
            $platform->getDateTimeFormatString(),
            $value,
            DateTimeKernel::getTimeZoneDatabase()
        )) {
            $converted->setTimezone(DateTimeKernel::getTimeZoneClient() ?: DateTimeKernel::getTimeZoneServer());
        }

        if (!$converted) {
            throw ConversionException::conversionFailedFormat(
                $value,
                $this->getName(),
                $platform->getDateTimeFormatString()
            );
        }

        return $converted;
    }
}
