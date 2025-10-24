<?php

namespace EWZ\SymfonyAdminBundle\Controller\Admin\Api\Traits;

use Doctrine\Common\Annotations\AnnotationReader;
use EWZ\SymfonyAdminBundle\Annotation\ConfigField;
use EWZ\SymfonyAdminBundle\Util\ExportData;
use EWZ\SymfonyAdminBundle\Util\StringUtil;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\JsonResponse;

trait BulkExportTrait
{
    /**
     * @return int
     */
    public function getExportPageSize(): int
    {
        $pageSize = (int) $this->getParameter('symfony_admin.export_page_size') ?: ExportData::PAGE_SIZE;
        if ($pageSize <= 0) {
            $pageSize = ExportData::PAGE_SIZE;
        }

        return $pageSize;
    }

    /**
     * @param Packages $assetsManager
     * @param array    $objects
     *
     * @return JsonResponse
     */
    private function doBulkExport(Packages $assetsManager, array $objects): JsonResponse
    {
        // build columns and enum metadata via reflection of entity config annotations
        $objectClass = $this->getRepository()->getClass();
        $annotationReader = new AnnotationReader();
        $reflectionObject = new \ReflectionObject(new $objectClass());

        $columns = [];
        $enumColumns = [];

        foreach ($reflectionObject->getProperties() as $reflectionProperty) {
            $propertyAnnotation = $annotationReader->getPropertyAnnotation($reflectionProperty, ConfigField::class);

            if (null !== $propertyAnnotation) {
                if ($values = $propertyAnnotation->defaultValues['importexport'] ?? null) {
                    $name = $reflectionProperty->getName();
                    $header = $values['header'] ?? $reflectionProperty->getName();

                    if (isset($values['enum'])) {
                        $choices = $values['enum']::getChoices();

                        $enumColumns[$name] = [
                            'choices' => [],
                            'count' => 0,
                            'is_array' => $values['isArray'] ?? false,
                        ];

                        foreach ($choices as $value => $key) {
                            $enumColumns[$name]['choices'][$key] = $value;
                        }
                    }

                    $columns[$name] = $header;
                }
            }
        }

        // determine enumColumns[count] for is_array enum columns
        foreach ($objects as $item) {
            foreach ($enumColumns as $column => &$options) {
                if (!$options['is_array']) {
                    continue;
                }

                $method = sprintf('get%s', StringUtil::classify($column));

                $keys = [];
                foreach ($item->$method() as $entry) {
                    if (!isset($keys[$entry['key']])) {
                        $keys[$entry['key']] = 0;
                    }

                    ++$keys[$entry['key']];
                }

                $max = 0;
                foreach ($keys as $count) {
                    if ($max < $count) {
                        $max = $count;
                    }
                }

                if ($options['count'] < $max) {
                    $options['count'] = $max;
                }
            }
        }

        // initialize spreadsheet and header
        $spreadsheet = $this->initExportSpreadsheet($columns, $enumColumns);

        // append rows (objects -> rows conversion handled in appendExportRows)
        $this->appendExportRows($spreadsheet, $columns, $objects, $enumColumns, 2);

        return $this->finalizeExportSpreadsheet($assetsManager, $spreadsheet);
    }

    /**
     * @param array $columns
     * @param array $enumColumns
     *
     * @return Spreadsheet
     */
    private function initExportSpreadsheet(array $columns, array $enumColumns = []): Spreadsheet
    {
        return ExportData::initExportSpreadsheet($columns, $enumColumns);
    }

    /**
     * @param Spreadsheet $spreadsheet
     * @param array       $columns
     * @param array       $rows
     * @param array       $enumColumns
     * @param int         $startRow
     *
     * @return int
     */
    private function appendExportRows(Spreadsheet $spreadsheet, array $columns, array $rows, array $enumColumns = [], int $startRow = 2): int
    {
        $dateFormat = $this->getUser()
            ? $this->getUser()->getDateFormat()
            : null;

        return ExportData::appendExportRows($spreadsheet, $columns, $rows, $enumColumns, $startRow, $dateFormat);
    }

    /**
     * @param Packages    $assetsManager
     * @param Spreadsheet $spreadsheet
     *
     * @return JsonResponse
     */
    private function finalizeExportSpreadsheet(Packages $assetsManager, Spreadsheet $spreadsheet): JsonResponse
    {
        // save spreadsheet to temp file
        $tmpFile = ExportData::saveSpreadsheetToTempFile($spreadsheet);

        // upload temp file
        $fileName = $this->fileUploader->create($tmpFile, $this->getParameter('symfony_admin.upload_url'));

        // cleanup
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        @unlink($tmpFile);

        return $this->json([
            'ok' => true,
            'message' => $this->translator->trans('alert.export_completed'),
            'actionConfig' => [
                'title' => $this->translator->trans('link.download_file'),
                'href' => $assetsManager->getUrl($fileName),
                'target' => '_blank',
                'open' => true,
            ],
        ]);
    }
}
