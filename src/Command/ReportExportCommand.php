<?php

namespace EWZ\SymfonyAdminBundle\Command;

use Doctrine\Persistence\ManagerRegistry;
use EWZ\SymfonyAdminBundle\Entity\Report;
use EWZ\SymfonyAdminBundle\Entity\User;
use EWZ\SymfonyAdminBundle\Event\ReportExportedEvent;
use EWZ\SymfonyAdminBundle\Events;
use EWZ\SymfonyAdminBundle\FileUploader\FileUploaderInterface;
use EWZ\SymfonyAdminBundle\Report\AbstractReport;
use EWZ\SymfonyAdminBundle\Repository\ReportRepository;
use EWZ\SymfonyAdminBundle\Repository\UserRepository;
use EWZ\SymfonyAdminBundle\Util\ExportData;
use EWZ\SymfonyAdminBundle\Util\StringUtil;
use Pagerfanta\Pagerfanta;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ReportExportCommand extends Command
{
    /** @var ManagerRegistry */
    private $managerRegistry;

    /** @var UserRepository */
    private $userRepository;

    /** @var ReportRepository */
    private $reportRepository;

    /** @var EventDispatcherInterface */
    private $eventDispatcher;

    /** @var Packages */
    private $assetsManager;

    /** @var FileUploaderInterface */
    private $fileUploader;

    /** @var ParameterBagInterface */
    private $params;

    /**
     * @param ManagerRegistry          $managerRegistry
     * @param UserRepository           $userRepository
     * @param ReportRepository         $reportRepository
     * @param EventDispatcherInterface $eventDispatcher
     * @param Packages                 $assetsManager
     * @param FileUploaderInterface    $fileUploader
     * @param ParameterBagInterface    $params
     */
    public function __construct(
        ManagerRegistry $managerRegistry,
        UserRepository $userRepository,
        ReportRepository $reportRepository,
        EventDispatcherInterface $eventDispatcher,
        Packages $assetsManager,
        FileUploaderInterface $fileUploader,
        ParameterBagInterface $params
    ) {
        parent::__construct();

        $this->managerRegistry = $managerRegistry;
        $this->userRepository = $userRepository;
        $this->reportRepository = $reportRepository;
        $this->assetsManager = $assetsManager;
        $this->eventDispatcher = $eventDispatcher;
        $this->fileUploader = $fileUploader;
        $this->params = $params;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('admin:report:export')
            ->setDescription('Export report to XLSX (background job)')
            ->addArgument('userId', InputArgument::REQUIRED, 'User ID')
            ->addArgument('reportId', InputArgument::REQUIRED, 'Report ID')
            ->addArgument('criteria', InputArgument::OPTIONAL, 'Base64-encoded JSON criteria')
            ->addArgument('grouping', InputArgument::OPTIONAL, 'Base64-encoded groupingType')
            ->addArgument('sort', InputArgument::OPTIONAL, 'Base64-encoded sort')
            ->addOption('page-size', null, InputOption::VALUE_OPTIONAL, 'Rows per page', ExportData::PAGE_SIZE);
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // parse args
        $userId = $input->getArgument('userId');
        $reportId = $input->getArgument('reportId');
        $criteria = $this->decodeArg($input->getArgument('criteria'));
        $grouping = $this->decodeArg($input->getArgument('grouping'));
        $sort = $this->decodeArg($input->getArgument('sort'));

        $pageSize = (int) $input->getOption('page-size') ?: ExportData::PAGE_SIZE;

        /** @var User|null $user */
        $user = $this->getUserById($userId);
        if (!$user) {
            $output->writeln('<error>User not found</error>');

            return 2;
        }

        /** @var Report|null $report */
        $report = $this->getReportById($reportId);
        if (!$report) {
            $output->writeln('<error>Report not found</error>');

            return 2;
        }

        // instantiate concrete report class
        list($category, $name) = explode('_', str_replace('-', '_', $report->getToken()), 2);
        $class = sprintf('App\\Report\\%s\\%sReport',
            StringUtil::classify($category),
            StringUtil::classify($name)
        );

        /** @var AbstractReport $reportObject */
        $reportObject = new $class($this->managerRegistry, $user);
        $reportObject->setCriteria($criteria ?: []);
        $reportObject->setGroupingType($grouping ?: null);
        $reportObject->setSort($sort ?: null);

        // build export columns metadata
        $columnsMeta = $reportObject->getExportVisibleColumns();
        if (0 === \count($columnsMeta)) {
            $output->writeln('<comment>No columns to export</comment>');

            return 0;
        }

        // build columns and enum metadata
        $columns = [];
        $enumColumns = [];
        foreach ($columnsMeta as $column => $options) {
            // set label
            $columns[$column] = $options['label'];

            // enum array metadata used by trait to expand columns
            if (($options['format'] ?? '') === 'enum' && ($options['options']['isArray'] ?? false)) {
                // if caller wants enum-array handling they should provide choices via enumClass
                $enumClass = $options['options']['class'] ?? null;
                if ($enumClass && method_exists($enumClass, 'getChoices')) {
                    $choices = $enumClass::getChoices();
                    $enumColumns[$column] = [
                        'choices' => [],
                        'count' => 0,
                        'is_array' => true,
                    ];

                    foreach ($choices as $value => $key) {
                        $enumColumns[$column]['choices'][$key] = $value;
                    }
                }
            }
        }

        // determine total rows using the repository's pager if available
        $reportObject->setPage(1);
        $reportObject->setLimit($pageSize);

        $searchResult = $reportObject->search();
        $total = 0;
        if ($searchResult instanceof Pagerfanta) {
            $total = $searchResult->getNbResults();
        } elseif (\is_array($searchResult)) {
            $total = \count($searchResult);
        }
        if (0 === $total) {
            $output->writeln('<info>No rows found</info>');

            return 0;
        }

        // number of pages to iterate
        $pages = (int) max(1, ceil($total / $pageSize));
        $currentRow = 2; // header is on row 1
        $dateFormat = $user->getDateFormat();

        // initialize spreadsheet header
        $spreadsheet = ExportData::initExportSpreadsheet($columns, $enumColumns);

        // iterate and append per page (report->export() must return only current page rows)
        for ($page = 1; $page <= $pages; ++$page) {
            $reportObject->setPage($page);
            $reportObject->setLimit($pageSize);

            $rows = $reportObject->export();
            if (empty($rows)) {
                continue;
            }

            $currentRow = ExportData::appendExportRows($spreadsheet, $columns, $rows, $enumColumns, $currentRow, $dateFormat);

            $output->writeln(sprintf('Appended page %d/%d', $page, $pages));
        }

        // save to temp file
        $tmpFile = ExportData::saveSpreadsheetToTempFile($spreadsheet);

        // upload temp file
        $fileName = $this->fileUploader->create($tmpFile, $this->params->get('symfony_admin.upload_url'));
        $output->writeln(sprintf('<info>Uploaded to: %s</info>', $fileName));

        // cleanup
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        @unlink($tmpFile);

        $output->writeln('<info>Export complete</info>');

        // dispatch event so app can handle notification/processing
        $this->eventDispatcher->dispatch(
            new ReportExportedEvent($report, $user, $this->assetsManager->getUrl($fileName)),
            Events::REPORT_EXPORT_COMPLETED
        );

        return 0;
    }

    /**
     * @param string|null $arg
     *
     * @return mix
     */
    private function decodeArg(string $arg = null)
    {
        if (!$arg) {
            return null;
        }
        $decoded = base64_decode($arg);
        $json = json_decode($decoded, true);

        return null === $json ? $arg : $json;
    }

    /**
     * @param string $userId
     *
     * @return User|null
     */
    private function getUserById(string $userId): ?User
    {
        if (!$userId) {
            return null;
        }

        return $this->userRepository->find($userId);
    }

    /**
     * @param string $reportId
     *
     * @return Report|null
     */
    private function getReportById(string $reportId): ?Report
    {
        if (!$reportId) {
            return null;
        }

        return $this->reportRepository->find($reportId);
    }
}
