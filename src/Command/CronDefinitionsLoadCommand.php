<?php

namespace EWZ\SymfonyAdminBundle\Command;

use EWZ\SymfonyAdminBundle\Model\CronSchedule;
use EWZ\SymfonyAdminBundle\Repository\CronScheduleRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CronDefinitionsLoadCommand extends Command
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
            ->setName('admin:cron:definitions:load')
            ->setDescription('Loads cron commands definitions from application to database')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>Removing all previously loaded commands...</info>');

        $this->cronScheduleRepository->clearCommands();

        $applicationCommands = $this->getApplication()->all('admin:cron');

        foreach ($applicationCommands as $name => $command) {
            $output->write(sprintf('Processing command "<info>%s</info>": ', $name));
            if ($this->checkCommand($output, $command)) {
                $schedule = $this->createSchedule($output, $command, $name);

                $this->cronScheduleRepository->update($schedule);
            }
        }

        return 0;
    }

    /**
     * @param OutputInterface      $output
     * @param CronCommandInterface $command
     * @param string               $name
     * @param array                $arguments
     *
     * @return CronSchedule
     */
    private function createSchedule(
        OutputInterface $output,
        CronCommandInterface $command,
        string $name,
        array $arguments = []
    ): CronSchedule {
        $output->writeln('<comment>setting up schedule..</comment>');

        $schedule = $this->cronScheduleRepository->create();
        $schedule->setCommand($name);
        $schedule->setDefinition($command->getDefaultDefinition());
        $schedule->setArguments($arguments);

        return $schedule;
    }

    /**
     * @param OutputInterface $output
     * @param Command         $command
     *
     * @return bool
     */
    private function checkCommand(OutputInterface $output, Command $command): bool
    {
        if (!$command instanceof CronCommandInterface) {
            $output->writeln('<info>Skipping, the command does not implement CronCommandInterface</info>');

            return false;
        }

        if (!$command->getDefaultDefinition()) {
            $output->writeln('<error>no cron definition found, check command</error>');

            return false;
        }

        return true;
    }
}
