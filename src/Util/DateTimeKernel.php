<?php

namespace EWZ\SymfonyAdminBundle\Util;

final class DateTimeKernel
{
    /** @var \DateTimeImmutable[] */
    private static $dateTimeServer = [];

    /** @var \DateTimeZone[] */
    private static $timeZonesServer = [];

    /** @var \DateTimeZone */
    private static $timeZoneDatabase = null;

    /** @var \DateTimeZone */
    private static $timeZoneClient = null;

    /**
     * @return \DateTimeImmutable
     */
    public static function getDateTimeServer(): \DateTimeImmutable
    {
        $serverTimeZone = self::getTimeZoneServer();
        $timezoneKey = crc32($serverTimeZone->getName());

        if (array_key_exists($timezoneKey, self::$dateTimeServer)) {
            return self::$dateTimeServer[$timezoneKey];
        }

        $dateTimeImmutable = array_slice(self::$dateTimeServer, 0, 1)[0];

        self::$dateTimeServer[$timezoneKey] = $dateTimeImmutable->setTimezone($serverTimeZone);

        return self::$dateTimeServer[$timezoneKey];
    }

    /**
     * @param \DateTimeImmutable $datetime
     */
    public static function setDateTimeServer(\DateTimeImmutable $datetime): void
    {
        $timezoneKey = crc32($datetime->getTimezone()->getName());

        self::$dateTimeServer[$timezoneKey] = $datetime;
    }

    /**
     * @return \DateTimeZone
     */
    public static function getTimeZoneServer(): \DateTimeZone
    {
        $timezoneKey = crc32(date_default_timezone_get());

        if (array_key_exists($timezoneKey, self::$timeZonesServer)) {
            return self::$timeZonesServer[$timezoneKey];
        }

        self::$timeZonesServer[$timezoneKey] = new \DateTimeZone(date_default_timezone_get());

        return self::$timeZonesServer[$timezoneKey];
    }

    /**
     * @return \DateTimeZone|null
     */
    public static function getTimeZoneDatabase(): ?\DateTimeZone
    {
        return self::$timeZoneDatabase;
    }

    /**
     * @param \DateTimeZone $timezone
     */
    public static function setTimeZoneDatabase(\DateTimeZone $timezone): void
    {
        self::$timeZoneDatabase = $timezone;
    }

    /**
     * @return \DateTimeZone|null
     */
    public static function getTimeZoneClient(): ?\DateTimeZone
    {
        return self::$timeZoneClient;
    }

    /**
     * @param \DateTimeZone $timezone
     */
    public static function setTimeZoneClient(\DateTimeZone $timezone): void
    {
        self::$timeZoneClient = $timezone;
    }
}
