<?php

namespace EWZ\SymfonyAdminBundle\Command;

use EWZ\SymfonyAdminBundle\Modal\CronSchedule;
use EWZ\SymfonyAdminBundle\Repository\CronScheduleRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractCronCommand extends Command implements CronCommandInterface
{
    /** @var CronScheduleRepository */
    protected $cronScheduleRepository;

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
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var CronSchedule $cronSchedule */
        $cronSchedule = $this->cronScheduleRepository->findOneBy(['command' => $this->getName()]);

        if ($cronSchedule && !$cronSchedule->isEnabled()) {
            $output->writeln('<error>Command already running</error>');

            return;
        }

        $cronSchedule->setEnabled(false);
        $this->cronScheduleRepository->update($cronSchedule);

        $this->processCommand($input, $output);

        $cronSchedule->setEnabled(true);
        $this->cronScheduleRepository->update($cronSchedule);

        return 0;
    }

    /**
     * Process the current command.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    abstract protected function processCommand(InputInterface $input, OutputInterface $output): void;
}
