<?php

namespace EWZ\SymfonyAdminBundle\Controller\Admin\Api\Traits;

use Ddeboer\DataImport\Writer\ExcelWriter;
use Doctrine\Common\Annotations\AnnotationReader;
use EWZ\SymfonyAdminBundle\Annotation\ConfigField;
use EWZ\SymfonyAdminBundle\Util\StringUtil;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\KernelInterface;

trait BulkExportTrait
{
    /**
     * @param KernelInterface $kernel
     * @param Packages        $assetsManager
     * @param array           $objects
     *
     * @return JsonResponse
     */
    private function doBulkExport(KernelInterface $kernel, Packages $assetsManager, array $objects): JsonResponse
    {
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

        // load all rows (use pagination)
        $result = [];

        foreach ($objects as $item) {
            $result[] = $item;

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

        // generate filename
        $fileName = sprintf('%s/%s.xlsx', $this->getParameter('pending_url'), Uuid::uuid4());

        // create an Excel file
        $file = new \SplFileObject(sprintf('%s/%s', $kernel->getPublicDir(), $fileName), 'w');
        $writer = new ExcelWriter($file);

        $writer->prepare();

        // header
        $row = [];
        foreach ($columns as $column => $header) {
            if (isset($enumColumns[$column]) && $enumColumns[$column]['is_array']) {
                for ($i = 0; $i < $enumColumns[$column]['count']; ++$i) {
                    foreach ($enumColumns[$column]['choices'] as $key => $value) {
                        $custom = sprintf('%s__%s_%d', $column, $key, $i);
                        $row[$custom] = $value;
                    }
                }
            } else {
                $row[$column] = $header;
            }
        }

        // add headers
        $writer->writeItem(array_values($row));

        foreach ($result as $item) {
            $row = [];
            foreach (array_keys($columns) as $column) {
                $method = sprintf('get%s', StringUtil::classify($column));
                if (!method_exists($item, $method)) {
                    $method = sprintf('is%s', StringUtil::classify($column));
                }

                $data = $item->$method();

                if ($data instanceof \DateTimeInterface) {
                    $data = $data->format($this->getUser()->getDateFormat());
                }

                if (isset($enumColumns[$column])) {
                    if ($enumColumns[$column]['is_array']) {
                        for ($i = 0; $i < $enumColumns[$column]['count']; ++$i) {
                            foreach ($enumColumns[$column]['choices'] as $key => $value) {
                                $custom = sprintf('%s__%s_%d', $column, $key, $i);

                                $found = false;
                                foreach ($data as $index => $entry) {
                                    if ($entry['key'] == $key) {
                                        $found = true;

                                        $row[$custom] = $entry['value'];

                                        unset($data[$index]);

                                        break;
                                    }
                                }

                                if (!$found) {
                                    $row[$custom] = null;
                                }
                            }
                        }
                    } else {
                        $row[$column] = $enumColumns[$column]['choices'][$data] ?? null;
                    }
                } elseif (is_numeric($data) || is_bool($data)) {
                    $row[$column] = $data;
                } else {
                    $row[$column] = (string) $data ?: null;
                }
            }

            // add data
            $writer->writeItem($row);
        }

        $writer->finish();

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
