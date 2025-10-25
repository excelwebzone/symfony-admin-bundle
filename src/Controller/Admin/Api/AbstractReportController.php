<?php

namespace EWZ\SymfonyAdminBundle\Controller\Admin\Api;

use EWZ\SymfonyAdminBundle\Controller\Admin\Api\Traits\BulkExportTrait;
use EWZ\SymfonyAdminBundle\Model\Report;
use EWZ\SymfonyAdminBundle\Report\AbstractReport;
use EWZ\SymfonyAdminBundle\Util\CommandRunner;
use EWZ\SymfonyAdminBundle\Util\StringUtil;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;

abstract class AbstractReportController extends AbstractController
{
    use BulkExportTrait;

    /**
     * @param Request $request
     * @param Report  $report
     *
     * @return JsonResponse
     */
    public function chart(Request $request, Report $report): JsonResponse
    {
        // get consts
        $groupingType = $request->query->get('groupingType', 'monthly');

        // convert request filters into query
        $criteria = json_decode($request->query->get('filters', '[]'), true);

        /** @var AbstractReport $reportObject */
        $reportObject = $this->getReportObject($report);

        // remove compare from criteria
        if ($reportObject->getCompareField()) {
            unset($criteria[$reportObject->getCompareField()]);
        }

        $reportObject->setCriteria($criteria);
        $reportObject->setGroupingType($groupingType);

        list($totals, $items, $labels) = $reportObject->chart();

        return $this->json([
            'ok' => true,
            'total' => $totals,
            'items' => $items,
            'labels' => $labels,
        ]);
    }

    /**
     * @param Request $request
     * @param KernelInterface kernel
     * @param Report $report
     *
     * @return JsonResponse
     */
    public function export(Request $request, KernelInterface $kernel, Report $report): JsonResponse
    {
        // get consts
        $groupingType = $request->query->get('groupingType', 'monthly');
        $sort = $request->query->get('sort');

        // convert request filters into query
        $criteria = json_decode($request->query->get('filters', '[]'), true);

        /** @var AbstractReport $reportObject */
        $reportObject = $this->getReportObject($report);

        // remove compare from criteria
        if ($reportObject->getCompareField()) {
            unset($criteria[$reportObject->getCompareField()]);
        }

        $reportObject->setCriteria($criteria);
        $reportObject->setGroupingType($groupingType);
        $reportObject->setSort($sort);

        // build export columns metadata
        $columnsMeta = $reportObject->getExportVisibleColumns();
        if (0 === \count($columnsMeta)) {
            return $this->json([
                'ok' => true,
                'message' => $this->translator->trans('alert.no_results_found'),
            ]);
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

        // pagination parameters — tune page size to balance DB and memory
        $pageSize = $this->getExportPageSize();

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
            return $this->json([
                'ok' => true,
                'message' => $this->translator->trans('alert.no_results_found'),
            ]);
        }

        // @hack
        $_SERVER['argv'] = [
            sprintf('%s/bin/console', $kernel->getProjectDir()),
        ];

        CommandRunner::runCommand(
            'admin:report:export',
            array_merge(
                [
                    $this->getUser()->getId(),
                    $report->getId(),
                    base64_encode(json_encode($reportObject->getCriteria())),
                    $reportObject->getGroupingType()
                        ? base64_encode(json_encode($reportObject->getGroupingType()))
                        : null,
                    $reportObject->getSort()
                        ? base64_encode(json_encode($reportObject->getSort()))
                        : null,
                ],
                ['--env' => $kernel->getEnvironment()]
            )
        );

        return $this->json([
            'ok' => true,
            'message' => $this->translator->trans('alert.export_scheduled'),
        ]);
    }

    /**
     * @param Request $request
     * @param Report  $report
     *
     * @return JsonResponse
     */
    public function findAll(Request $request, Report $report): JsonResponse
    {
        // get consts
        $page = (int) $request->query->get('page', 1);
        $limit = (int) $request->query->get('limit', 20);
        $sort = $request->query->get('sort');
        $groupingType = $request->query->get('groupingType', 'monthly');
        $showTotals = 1 == $request->query->get('showTotals') && 1 === $page;
        $cardView = 1 == $request->query->get('cardView');

        // convert request filters into query
        $criteria = json_decode($request->query->get('filters', '[]'), true);

        // set the report template (columns)
        $template = $this->getReportTemplate($report, $cardView);

        /** @var AbstractReport $reportObject */
        $reportObject = $this->getReportObject($report);
        $reportObject->setCriteria($criteria);
        $reportObject->setPage($page);
        $reportObject->setLimit($limit);
        $reportObject->setSort($sort);
        $reportObject->setGroupingType($groupingType);

        if ($cardView) {
            list($items, $columns) = $reportObject->getCards();
        } else {
            /** @var Pagerfanta|array $items */
            $items = $reportObject->search();

            /** @var array $totals */
            $totals = $showTotals
                ? $reportObject->searchTotals($reportObject->getTotalData() ?: $items)
                : [];
        }

        // convert to Pagerfanta
        if (\is_array($items)) {
            $adapter = new ArrayAdapter($items);
            $pagerfanta = new Pagerfanta($adapter);

            if (\count($items)) {
                $pagerfanta->setMaxPerPage(\count($items));
            }

            $items = $pagerfanta;
        }

        /** @var array $compareItems */
        $compareItems = $reportObject->searchCompare($items ?: []);

        $html = $this->renderView($template, [
            'report' => $report,
            'criteria' => $criteria,
            'items' => $items,
            'compareItems' => $compareItems,
        ]);

        $data = [
            'html' => $html,
            'page' => $page,
            'count' => $items ? \count($items->getCurrentPageResults()) : 0,
            'total' => $items ? $items->getNbResults() : 0,
        ];

        if (1 === $page && isset($totals)) {
            $data['totals'] = $totals;
        }

        if (isset($columns)) {
            $data['count'] = 0;
            foreach ($items as $item) {
                $data['count'] += \count($item);
            }

            $data['total'] = 0;
            foreach ($columns as $rows) {
                $data['total'] += $rows;
            }

            if (1 === $page) {
                $data['columns'] = $columns;
            }
        }

        return $this->json(array_merge($data, [
            'ok' => true,
        ]));
    }

    /**
     * @param Report $report
     * @param bool   $cardView
     *
     * @return string
     */
    protected function getReportTemplate(Report $report, bool $cardView = false): string
    {
        list($category, $name) = explode('_', str_replace('-', '_', $report->getToken()), 2);

        return sprintf('admin/partial/report/%s/%s%s.html.twig',
            strtolower(StringUtil::tableize($category)),
            strtolower(StringUtil::tableize($name)),
            $cardView ? '_card' : null
        );
    }

    /**
     * @param Report $report
     *
     * @return AbstractReport
     */
    private function getReportObject(Report $report): AbstractReport
    {
        list($category, $name) = explode('_', str_replace('-', '_', $report->getToken()), 2);

        $class = sprintf('App\\Report\\%s\\%sReport',
            StringUtil::classify($category),
            StringUtil::classify($name)
        );

        return new $class($this->managerRegistry, $this->getUser());
    }
}
