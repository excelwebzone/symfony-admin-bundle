<?php

namespace EWZ\SymfonyAdminBundle\Util;

use Doctrine\Common\Collections\Collection;
use EWZ\SymfonyAdminBundle\Model\User;
use PhpOffice\PhpSpreadsheet\Settings;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Uid\Uuid;

/**
 * Pure helper to manipulate PhpSpreadsheet objects and persist them to a temp file.
 * Instance methods so this class is usable as a service via DI.
 */
final class ExportData
{
    public const PAGE_SIZE = 1000;

    /**
     * Initialize a Spreadsheet, enable caching and write the header row.
     *
     * IMPORTANT: $columns is an associative map columnKey => headerLabel.
     * $enumColumns is used to expand multi-value columns into multiple headers.
     *
     * @param array $columns
     * @param array $enumColumns
     *
     * @return Spreadsheet
     */
    public static function initExportSpreadsheet(array $columns, array $enumColumns = []): Spreadsheet
    {
        // Best-effort: enable PSR-16 caching (Filesystem fallback) for PhpSpreadsheet if available.
        try {
            $tmpDir = ini_get('upload_tmp_dir') ?: sys_get_temp_dir();
            $psr6Pool = new FilesystemAdapter('', 0, $tmpDir);
            $psr16Cache = new Psr16Cache($psr6Pool);
            if (method_exists(Settings::class, 'setCache')) {
                Settings::setCache($psr16Cache);
            }
        } catch (\Throwable $e) {
            // Best-effort only — continue without fatal error.
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Build header row, expanding enum-array columns into multiple headers if needed
        $header = [];
        foreach ($columns as $column => $label) {
            if (isset($enumColumns[$column]) && !empty($enumColumns[$column]['is_array'])) {
                for ($i = 0; $i < $enumColumns[$column]['count']; ++$i) {
                    foreach ($enumColumns[$column]['choices'] as $key => $value) {
                        $custom = sprintf('%s__%s_%d', $column, $key, $i);
                        $header[$custom] = $value;
                    }
                }
            } else {
                $header[$column] = $label;
            }
        }

        // write header in A1
        $sheet->fromArray(array_values($header), null, 'A1');

        return $spreadsheet;
    }

    /**
     * Append normalized rows (array of associative arrays keyed by column keys) to an existing Spreadsheet.
     *
     * NOTE: $rows MUST be arrays of scalar/string values already formatted as needed.
     *
     * @param Spreadsheet $spreadsheet
     * @param array       $columns     associative columnKey => header
     * @param array       $rows        array of rows: [ [colKey=>val, ...], ... ]
     * @param array       $enumColumns enum metadata used to expand columns (same as init)
     * @param int         $startRow    Excel row number to start writing (1-based)
     * @param string|null $dateFormat  user date format (used to format DateTimeInterface values)
     *
     * @return int next available row number after appended rows
     */
    public static function appendExportRows(Spreadsheet $spreadsheet, array $columns, array $rows, array $enumColumns = [], int $startRow = 2, string $dateFormat = null): int
    {
        $sheet = $spreadsheet->getActiveSheet();
        $rowNumber = max(2, (int) $startRow);

        foreach ($rows as $rowData) {
            // Build flattened row matching header order and enum expansions
            $flat = [];
            foreach ($columns as $column => $label) {
                // Row data expected to be normalized (scalar/arrays). If it's an object, trait should normalize
                $data = $rowData[$column] ?? null;

                if ($data instanceof \DateTimeInterface) {
                    $data = $data->format($dateFormat ?? User::PHP_DATE_FORMAT_US);
                } elseif ($data instanceof Collection || $data instanceof \Traversable) {
                    $elements = [];
                    foreach ($data as $element) {
                        $elements[] = (string) $element;
                    }
                    $data = implode(', ', $elements);
                }

                if (isset($enumColumns[$column])) {
                    if (!empty($enumColumns[$column]['is_array'])) {
                        $d = $data ?: [];
                        for ($i = 0; $i < $enumColumns[$column]['count']; ++$i) {
                            foreach ($enumColumns[$column]['choices'] as $key => $value) {
                                $custom = sprintf('%s__%s_%d', $column, $key, $i);
                                $found = null;
                                if (\is_array($d)) {
                                    foreach ($d as $index => $entry) {
                                        if (isset($entry['key']) && $entry['key'] == $key) {
                                            $found = $entry['value'];
                                            unset($d[$index]);
                                            break;
                                        }
                                    }
                                }
                                $flat[$custom] = $found ?? null;
                            }
                        }
                    } else {
                        $flat[$column] = $enumColumns[$column]['choices'][$data] ?? null;
                    }
                } elseif (is_numeric($data) || \is_bool($data)) {
                    $flat[$column] = $data;
                } else {
                    $flat[$column] = (string) $data ?: null;
                }
            }

            // Write flat row into sheet at A{rowNumber}
            $sheet->fromArray(array_values($flat), null, sprintf('A%d', $rowNumber));

            // Periodically collect GC to keep memory low for very large exports
            if (0 === ($rowNumber % 5000) && \function_exists('gc_collect_cycles')) {
                @gc_collect_cycles();
            }

            ++$rowNumber;
        }

        return $rowNumber;
    }

    /**
     * Save the spreadsheet to a temporary XLSX file and return the path.
     *
     * Caller is responsible for uploading/deleting the returned file.
     *
     * @param Spreadsheet $spreadsheet
     *
     * @return string full path to temporary XLSX file
     *
     * @throws \Throwable when writer->save fails
     */
    public static function saveSpreadsheetToTempFile(Spreadsheet $spreadsheet): string
    {
        $writer = new Xlsx($spreadsheet);
        $tmpDir = ini_get('upload_tmp_dir') ?: sys_get_temp_dir();
        $excelName = sprintf('%s/%s.xlsx', rtrim($tmpDir, '/'), Uuid::v4());

        @set_time_limit(0);
        // Save may throw; let caller handle exceptions and cleanup
        $writer->save($excelName);

        return $excelName;
    }
}
