<?php

namespace EWZ\SymfonyAdminBundle\Repository;

use EWZ\SymfonyAdminBundle\Model\CronSchedule;

abstract class CronScheduleRepository extends AbstractRepository
{
    /**
     * @param CronSchedule[] $schedules
     * @param string[]       $arguments
     *
     * @return CronSchedule[]
     */
    public function filterByArguments(array $schedules, array $arguments): array
    {
        $argsSchedule = $this->create();
        $argsSchedule->setArguments($arguments);

        return array_filter(
            $schedules,
            function (CronSchedule $schedule) use ($argsSchedule) {
                return $schedule->getArgumentsHash() === $argsSchedule->getArgumentsHash();
            }
        );
    }

    /**
     * @param string $command
     * @param array  $arguments
     * @param string $definition
     *
     * @return bool
     */
    public function hasSchedule(string $command, array $arguments, string $definition): bool
    {
        $schedules = $this->searchAll([
            'command' => $command,
            'definition' => $definition,
        ]);

        $schedules = $this->filterByArguments($schedules, $arguments);

        return count($schedules) > 0;
    }

    /**
     * @param string $command
     * @param array  $arguments
     * @param string $definition
     *
     * @return CronSchedule
     */
    public function createSchedule(string $command, array $arguments, string $definition): CronSchedule
    {
        if (!$command || !$definition) {
            throw new \InvalidArgumentException('Parameters "command" and "definition" must be specified.');
        }

        if ($this->hasSchedule($command, $arguments, $definition)) {
            throw new \LogicException('Schedule with same parameters already exists.');
        }

        $schedule = $this->create();
        $schedule->setCommand($command);
        $schedule->setArguments($arguments);
        $schedule->setDefinition($definition);

        return $schedule;
    }

    /**
     * Clear commands and return numbers of rows cleared.
     *
     * @return int
     */
    public function clearCommands(): int
    {
        return $this->createQueryBuilder('q')
            ->delete()
            ->getQuery()
            ->execute()
        ;
    }
}
