<?php

namespace EWZ\SymfonyAdminBundle\Controller\Admin\Api;

use EWZ\SymfonyAdminBundle\Controller\Admin\Api\Traits\BulkExportTrait;
use EWZ\SymfonyAdminBundle\Model\Report;
use EWZ\SymfonyAdminBundle\Report\AbstractReport;
use EWZ\SymfonyAdminBundle\Util\StringUtil;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

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

        /** @var AbstractReport $report */
        $report = $this->getReportObject($report);
        $report->setCriteria($criteria);
        $report->setGroupingType($groupingType);

        list($totals, $items, $labels) = $report->chart();

        return $this->json([
            'ok' => true,
            'total' => $totals,
            'items' => $items,
            'labels' => $labels,
        ]);
    }

    /**
     * @param Request  $request
     * @param Packages $assetsManager
     * @param Report   $report
     *
     * @return JsonResponse
     */
    public function export(Request $request, Packages $assetsManager, Report $report): JsonResponse
    {
        // get consts
        $sort = $request->query->get('sort');

        // convert request filters into query
        $criteria = json_decode($request->query->get('filters', '[]'), true);

        /** @var AbstractReport $report */
        $report = $this->getReportObject($report);
        $report->setCriteria($criteria);
        $report->setSort($sort);

        /** @var array $items */
        $items = $report->export();

        // empty or header only
        if (1 >= count($items)) {
            return $this->json([
                'ok' => true,
                'message' => $this->translator->trans('alert.no_results_found'),
            ]);
        }

        // get headers
        $columns = $items[0];

        // remove header
        array_shift($items);

        return $this->generateExport($assetsManager, $columns, $items);
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

        /** @var AbstractReport $report */
        $report = $this->getReportObject($report);
        $report->setCriteria($criteria);
        $report->setPage($page);
        $report->setLimit($limit);
        $report->setSort($sort);
        $report->setGroupingType($groupingType);

        if ($cardView) {
            list($items, $columns) = $report->getCards();
        } else {
            /** @var Pagerfanta|array $items */
            $items = $report->search();

            /** @var array $totals */
            $totals = $showTotals
                ? $report->searchTotals($report->getTotalData() ?: $items)
                : [];
        }

        // convert to Pagerfanta
        if (is_array($items)) {
            $adapter = new ArrayAdapter($items);
            $pagerfanta = new Pagerfanta($adapter);

            if (count($items)) {
                $pagerfanta->setMaxPerPage(count($items));
            }

            $items = $pagerfanta;
        }

        $html = $this->renderView($template, [
            'criteria' => $criteria,
            'items' => $items,
        ]);

        $data = [
            'html' => $html,
            'page' => $page,
            'count' => $items ? count($items->getCurrentPageResults()) : 0,
            'total' => $items ? $items->getNbResults() : 0,
        ];

        if (1 === $page && isset($totals)) {
            $data['totals'] = $totals;
        }

        if (1 === $page && isset($columns)) {
            $data['columns'] = $columns;
        }

        return $this->json(array_merge($data, [
            'ok' => true,
        ]));
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

        return new $class($this->objectManager, $this->getUser());
    }

    /**
     * @param Report $report
     * @param bool   $cardView
     *
     * @return string
     */
    private function getReportTemplate(Report $report, bool $cardView = false): string
    {
        list($category, $name) = explode('_', str_replace('-', '_', $report->getToken()), 2);

        return sprintf('admin/partial/report/%s/%s%s.html.twig',
            strtolower(StringUtil::tableize($category)),
            strtolower(StringUtil::tableize($name)),
            $cardView ? '_card' : null
        );
    }
}
