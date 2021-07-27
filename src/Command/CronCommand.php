<?php

namespace EWZ\SymfonyAdminBundle\Command;

use Cron\CronExpression;
use Doctrine\Common\Collections\ArrayCollection;
use EWZ\SymfonyAdminBundle\Model\CronSchedule;
use EWZ\SymfonyAdminBundle\Repository\CronScheduleRepository;
use EWZ\SymfonyAdminBundle\Util\CommandRunner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The command used in the crontab configuration and in the interface which allows to define
 * the console commands execution schedule.
 *
 * ## Usage
 *
 * All you need is to add `admin:cron` command to a system cron (on *nix systems), for example:
 *
 * ``` bash
 * *\/1 * * * * /usr/local/bin/php /path/to/bin/console --env=prod admin:cron >> /dev/null
 * ``
 *
 * On Windows you can use Task Scheduler from Control Panel.
 *
 * If you want to make your console command auto-scheduled need to do following:
 *
 * - add new record to modal `EWZ\SymfonyAdminBundle\Model\CronSchedule`
 */
class CronCommand extends Command
{
    /** @var CronScheduleRepository */
    private $cronScheduleRepository;

    /**
     * @param CronScheduleRepository $cronScheduleRepository
     */
    public function __construct(CronScheduleRepository $cronScheduleRepository)
    {
        parent::__construct();

        $this->cronScheduleRepository = $cronScheduleRepository;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('admin:cron')
            ->setDescription('Cron commands launcher')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $schedules = $this->getAllSchedules();

        /** @var CronSchedule $schedule */
        foreach ($schedules as $schedule) {
            $cronExpression = $this->createCron($schedule->getDefinition());
            if ($cronExpression->isDue()) {
                /** @var CronCommandInterface $command */
                $command = $this->getApplication()->get($schedule->getCommand());

                // ignore inactive commands
                if ($command instanceof CronCommandInterface && !$command->isActive()) {
                    $output->writeln(
                        sprintf('Skipping not enabled command %s', $schedule->getCommand()),
                        OutputInterface::VERBOSITY_DEBUG
                    );

                    continue;
                }

                // in case of common cron command - send the MQ message that will run this command
                $output->writeln(
                    sprintf('Scheduling run for command %s', $schedule->getCommand()),
                    OutputInterface::VERBOSITY_DEBUG
                );

                CommandRunner::runCommand(
                    $schedule->getCommand(),
                    array_merge(
                        $this->resolveOptions($schedule->getArguments()),
                        ['--env' => $_SERVER['APP_ENV'] ?? 'dev']
                    )
                );
            } else {
                $output->writeln(
                    sprintf('Skipping not due command %s', $schedule->getCommand()),
                    OutputInterface::VERBOSITY_DEBUG
                );
            }
        }

        $output->writeln('All commands scheduled', OutputInterface::VERBOSITY_DEBUG);

        return 0;
    }

    /**
     * Convert command arguments to options. It needed for correctly pass this arguments into ArrayInput:
     * new ArrayInput(['name' => 'foo', '--bar' => 'foobar']);.
     *
     * @param array $commandOptions
     *
     * @return array
     */
    protected function resolveOptions(array $commandOptions): array
    {
        $options = [];
        foreach ($commandOptions as $key => $option) {
            $params = explode('=', $option, 2);
            if (\is_array($params) && 2 === \count($params)) {
                $options[$params[0]] = $params[1];
            } else {
                $options[$key] = $option;
            }
        }

        return $options;
    }

    /**
     * @return CronSchedule[]|ArrayCollection
     */
    private function getAllSchedules(): ArrayCollection
    {
        return new ArrayCollection($this->cronScheduleRepository->findAll());
    }

    /**
     * Create a new CronExpression.
     *
     * @param string $definition The CRON expression to create.  There are
     *                           several special predefined values which can be used to substitute the
     *                           CRON expression:
     *
     *  `@yearly` (or `@annually`) Run once a year at midnight in the morning of January 1                 0 0 1 1 *
     *  `@monthly`                 Run once a month at midnight in the morning of the first of the month   0 0 1 * *
     *  `@weekly`                  Run once a week at midnight in the morning of Sunday                    0 0 * * 0
     *  `@daily`                   Run once a day at midnight                                              0 0 * * *
     *  `@hourly`                  Run once an hour at the beginning of the hour                           0 * * * *
     *
     * @return CronExpression
     */
    private function createCron(string $definition): CronExpression
    {
        return CronExpression::factory($definition);
    }
}
