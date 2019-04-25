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
     * @param Request         $request
     * @param KernelInterface $kernel
     * @param Packages        $assetsManager
     * @param Report          $report
     *
     * @return JsonResponse
     */
    public function export(Request $request, KernelInterface $kernel, Packages $assetsManager, Report $report): JsonResponse
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

        if (0 === count($items)) {
            return $this->json([
                'ok' => false,
                'error' => [
                    'message' => $this->translator->trans('error.no_data_found'),
                ],
            ]);
        }

        // get headers
        $columns = $items[0];

        // remove header
        array_shift($items);

        return $this->generateExport($kernel, $assetsManager, $columns, $items);
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

        // convert request filters into query
        $criteria = json_decode($request->query->get('filters', '[]'), true);

        // set the report template (columns)
        $template = $this->getReportTemplate($report);

        /** @var AbstractReport $report */
        $report = $this->getReportObject($report);
        $report->setCriteria($criteria);
        $report->setPage($page);
        $report->setLimit($limit);
        $report->setSort($sort);
        $report->setGroupingType($groupingType);

        /** @var Pagerfanta|array $items */
        $items = $report->search();

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
        list($group, $name) = explode('_', str_replace('-', '_', $report->getToken()), 2);

        $class = sprintf('App\\Report\\%s\\%sReport',
            StringUtil::classify($group),
            StringUtil::classify($name)
        );

        return new $class($this->objectManager);
    }

    /**
     * @param Report $report
     *
     * @return string
     */
    private function getReportTemplate(Report $report): string
    {
        list($group, $name) = explode('_', str_replace('-', '_', $report->getToken()), 2);

        return sprintf('admin/partial/report/%s/%s.html.twig',
            strtolower(StringUtil::tableize($group)),
            strtolower(StringUtil::tableize($name))
        );
    }
}