<?php

namespace EWZ\SymfonyAdminBundle\Command;

interface CronCommandInterface
{
    /**
     * Define default cron schedule definition for a command.
     * Example: "5 * * * *".
     *
     * @see \EWZ\SymfonyAdminBundle\Model\CronSchedule::setDefinition()
     *
     * @return string
     */
    public function getDefaultDefinition(): string;

    /**
     * Checks if the command active (i.e. properly configured etc).
     *
     * @return bool
     */
    public function isActive(): bool;
}
