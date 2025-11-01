<?php

namespace EWZ\SymfonyAdminBundle\Controller\Admin\Api\Traits;

use Doctrine\Common\Annotations\AnnotationReader;
use EWZ\SymfonyAdminBundle\Annotation\ConfigField;
use EWZ\SymfonyAdminBundle\Util\ExportData;
use EWZ\SymfonyAdminBundle\Util\StringUtil;
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

        // initialize CSV and header
        $csvFile = $this->initCsvExport($columns, $enumColumns);

        // append rows (objects -> rows conversion handled in ExportData::appendCsvRows)
        $this->appendCsvRows($csvFile, $columns, $objects, $enumColumns);

        return $this->finalizeCsvExport($assetsManager, $csvFile);
    }

    /**
     * Initialize CSV file and write header.
     *
     * @param array $columns
     * @param array $enumColumns
     *
     * @return string path to temp CSV
     */
    private function initCsvExport(array $columns, array $enumColumns = []): string
    {
        return ExportData::initCsvExport($columns, $enumColumns);
    }

    /**
     * Append rows to an existing CSV file.
     *
     * @param string $csvFilePath
     * @param array  $columns
     * @param array  $rows
     * @param array  $enumColumns
     */
    private function appendCsvRows(string $csvFilePath, array $columns, array $rows, array $enumColumns = []): void
    {
        $dateFormat = $this->getUser()
            ? $this->getUser()->getDateFormat()
            : null;

        ExportData::appendCsvRows($csvFilePath, $columns, $rows, $enumColumns, $dateFormat);
    }

    /**
     * Finalize CSV file: optional compression/perm setting handled in ExportData::closeCsvFile,
     * upload finalized file and cleanup temporary file.
     *
     * @param Packages $assetsManager
     * @param string   $csvFilePath
     *
     * @return JsonResponse
     */
    private function finalizeCsvExport(Packages $assetsManager, string $csvFilePath): JsonResponse
    {
        // close/finalize CSV (no gzip/chmod by default)
        $tmpFile = ExportData::closeCsvFile($csvFilePath);

        // upload temp file
        $fileName = $this->fileUploader->create($tmpFile, $this->getParameter('symfony_admin.upload_url'));

        // cleanup temporary file
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
