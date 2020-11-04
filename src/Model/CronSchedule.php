<?php

namespace EWZ\SymfonyAdminBundle\Model;

class CronSchedule
{
    /** @var int */
    protected $id;

    /** @var string */
    protected $command;

    /** @var array */
    protected $arguments = [];

    /** @var string */
    protected $argumentsHash;

    /** @var string */
    protected $definition;

    /** @var bool */
    protected $enabled = true;

    /**
     * @return string
     */
    public function __toString(): string
    {
        return (string) $this->getId();
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getCommand(): string
    {
        return $this->command;
    }

    /**
     * @param string $command
     */
    public function setCommand(string $command): void
    {
        $this->command = $command;
    }

    /**
     * @return array
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * @param array $arguments
     */
    public function setArguments(array $arguments): void
    {
        sort($arguments);

        $this->arguments = $arguments;
        $this->argumentsHash = md5(json_encode($arguments));
    }

    /**
     * @return string
     */
    public function getArgumentsHash(): string
    {
        return $this->argumentsHash;
    }

    /**
     * Returns cron definition string.
     *
     * @return string
     */
    public function getDefinition(): string
    {
        return $this->definition;
    }

    /**
     * Set cron definition string.
     *
     * General format:
     * *    *    *    *    *
     * ┬    ┬    ┬    ┬    ┬
     * │    │    │    │    │
     * │    │    │    │    │
     * │    │    │    │    └───── day of week (0 - 6) (0 to 6 are Sunday to Saturday, or use names)
     * │    │    │    └────────── month (1 - 12)
     * │    │    └─────────────── day of month (1 - 31)
     * │    └──────────────────── hour (0 - 23)
     * └───────────────────────── min (0 - 59)
     *
     * Predefined values are:
     *
     *  @yearly (or @annually)  Run once a year at midnight in the morning of January 1                 0 0 1 1 *
     *  @monthly                Run once a month at midnight in the morning of the first of the month   0 0 1 * *
     *  @weekly                 Run once a week at midnight in the morning of Sunday                    0 0 * * 0
     *  @daily                  Run once a day at midnight                                              0 0 * * *
     *  @hourly                 Run once an hour at the beginning of the hour                           0 * * * *
     *
     * @param string $definition New cron definition
     */
    public function setDefinition(string $definition): void
    {
        $this->definition = $definition;
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @param bool|null $boolean
     */
    public function setEnabled(bool $boolean = null): void
    {
        $this->enabled = (bool) $boolean;
    }
}
