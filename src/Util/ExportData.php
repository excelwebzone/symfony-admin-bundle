<?php

namespace EWZ\SymfonyAdminBundle\Util;

use Doctrine\Common\Collections\Collection;
use EWZ\SymfonyAdminBundle\Model\User;
use OpenSpout\Writer\Common\Creator\WriterEntityFactory;
use Symfony\Component\Uid\Uuid;

/**
 * Helper to build CSV exports to a temp file.
 * Instance methods so this class is usable as a service via DI.
 */
final class ExportData
{
    public const PAGE_SIZE = 500;

    /**
     * Initialize a CSV file, write the header row and return the path to the temp CSV file.
     *
     * IMPORTANT: $columns is an associative map columnKey => headerLabel.
     * $enumColumns is used to expand multi-value columns into multiple headers.
     *
     * @param array       $columns
     * @param array       $enumColumns
     * @param string|null $tmpDir      optional override for temporary directory
     *
     * @return string full path to temporary CSV file (already contains header row)
     */
    public static function initCsvExport(array $columns, array $enumColumns = [], string $tmpDir = null): string
    {
        $tmpDir = $tmpDir ?: (ini_get('upload_tmp_dir') ?: sys_get_temp_dir());
        $csvName = sprintf('%s/%s.csv', rtrim($tmpDir, '/'), Uuid::v4());

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

        $fp = @fopen($csvName, 'w');
        if (false === $fp) {
            throw new \RuntimeException(sprintf('Unable to create temporary CSV file "%s".', $csvName));
        }

        // UTF-8 BOM for Excel
        fwrite($fp, "\xEF\xBB\xBF");

        // Use RFC4180-compatible defaults: comma delimiter, double-quote enclosure
        fputcsv($fp, array_values($header));

        fclose($fp);

        return $csvName;
    }

    /**
     * Append normalized rows (array of associative arrays keyed by column keys) to an existing CSV file.
     *
     * NOTE: $rows MUST be arrays of scalar/string values already formatted as needed.
     *
     * @param string      $csvFilePath full path returned by initCsvExport()
     * @param array       $columns     associative columnKey => header
     * @param array       $rows        array of rows: [ [colKey=>val, ...], ... ]
     * @param array       $enumColumns enum metadata used to expand columns (same as init)
     * @param string|null $dateFormat  user date format (used to format DateTimeInterface values)
     */
    public static function appendCsvRows(
        string $csvFilePath,
        array $columns,
        array $rows,
        array $enumColumns = [],
        string $dateFormat = null
    ): void {
        $fp = @fopen($csvFilePath, 'a');
        if (false === $fp) {
            throw new \RuntimeException(sprintf('Unable to open CSV file "%s" for appending.', $csvFilePath));
        }

        $counter = 0;
        foreach ($rows as $rowData) {
            // If row is an entity/object, convert it to a simple array keyed by $columns
            if (!\is_array($rowData)) {
                $obj = $rowData;
                $rowData = [];
                foreach ($columns as $column => $_) {
                    $uc = StringUtil::classify($column);
                    $value = null;

                    foreach ([sprintf('get%s', $uc), sprintf('is%s', $uc), $column] as $method) {
                        if (\is_object($obj) && method_exists($obj, $method)) {
                            $value = $obj->$method();
                            break;
                        }
                    }

                    $rowData[$column] = $value;
                }
            }

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

            // Prepare output row with sanitization
            $out = [];
            foreach (array_values($flat) as $v) {
                if (null === $v) {
                    $out[] = null;
                    continue;
                }
                if (\is_bool($v) || \is_int($v) || \is_float($v)) {
                    $out[] = $v;
                    continue;
                }

                $s = (string) $v;

                // Neutralize CSV/Excel formula injection
                if ('' !== $s && "'" !== $s[0] && preg_match('/^[\p{Z}\x00-\x1F]*[=+\-@]/u', $s)) {
                    $s = "'".$s;
                }

                // UTF-8 normalization (best-effort)
                if (\function_exists('mb_check_encoding') && !mb_check_encoding($s, 'UTF-8')) {
                    if (\function_exists('mb_convert_encoding')) {
                        $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8,CP949,EUC-KR,CP1252,ISO-8859-1');
                    } elseif (\function_exists('iconv')) {
                        $s = iconv('UTF-8', 'UTF-8//IGNORE', $s);
                    }
                }

                // Strip NULs and non-printable control chars (keep \t and \n)
                $s = str_replace("\0", '', $s);
                $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $s);

                // Normalize newlines
                $s = str_replace(["\r\n", "\r"], "\n", $s);

                $out[] = $s;
            }

            // Write CSV row
            fputcsv($fp, $out);

            // Periodically collect GC to keep memory low for very large exports
            if (0 === ($counter % 5000) && \function_exists('gc_collect_cycles')) {
                @gc_collect_cycles();
            }

            ++$counter;
        }

        fclose($fp);
    }

    /**
     * Close and finalize the export file.
     *
     * By default this will convert the CSV to an XLSX file using OpenSpout (streaming, low memory), then optionally gzip
     * the resulting file and remove the uncompressed original. When $gzip is true, the returned path will be the ".gz"
     * file; otherwise it will be the path to the final uncompressed file (".xlsx" when $convertToXlsx is true, else ".csv").
     *
     * @param string   $csvFilePath          full path to the temporary CSV file produced by initCsvExport/appendCsvRows
     * @param int|null $chmod                Optional permissions to set on the final file (e.g. 0640). Null to skip chmod.
     * @param bool     $gzip                 whether to create a gzipped copy of the final file and return its path
     * @param bool     $removeOriginalOnGzip whether to remove the uncompressed final file after gzip is created
     * @param bool     $convertToXlsx        Whether to convert the CSV to XLSX before optional gzip. Uses OpenSpout streaming.
     *
     * @return string Path to the finalized file. Returns "<final>.<gz>" when $gzip is true, otherwise the path to "<final>".
     *
     * @throws \RuntimeException when the input CSV is missing/unreadable or compression fails
     */
    public static function closeCsvFile(
        string $csvFilePath,
        int $chmod = null,
        bool $gzip = false,
        bool $removeOriginalOnGzip = false,
        bool $convertToXlsx = true
    ): string {
        if (!file_exists($csvFilePath) || !is_readable($csvFilePath)) {
            throw new \RuntimeException(sprintf('CSV file "%s" is not readable or does not exist.', $csvFilePath));
        }

        $finalPath = $csvFilePath;

        if ($convertToXlsx) {
            $xlsxPath = self::convertCsvToXlsx($csvFilePath);
            if (null !== $chmod) {
                @chmod($xlsxPath, $chmod);
            }

            // remove CSV after successful conversion
            @unlink($csvFilePath);

            $finalPath = $xlsxPath;
        }

        if ($gzip) {
            $gzPath = $finalPath.'.gz';

            $in = @fopen($finalPath, 'r');
            if (false === $in) {
                throw new \RuntimeException(sprintf('Unable to open CSV file "%s" for compression.', $csvFilePath));
            }

            $out = @gzopen($gzPath, 'wb9');
            if (false === $out) {
                fclose($in);
                throw new \RuntimeException(sprintf('Unable to create gzip file "%s".', $gzPath));
            }

            while (!feof($in)) {
                $chunk = fread($in, 1024 * 512);
                if (false === $chunk) {
                    break;
                }
                gzwrite($out, $chunk);
            }

            fclose($in);
            gzclose($out);

            if ($removeOriginalOnGzip) {
                @unlink($finalPath);
            }

            if (null !== $chmod) {
                @chmod($gzPath, $chmod);
            }

            return $gzPath;
        }

        if (null !== $chmod) {
            // best-effort chmod; ignore failure
            @chmod($finalPath, $chmod);
        }

        return $finalPath;
    }

    /**
     * Stream-convert a CSV file to XLSX using OpenSpout.
     *
     * Reads the CSV as UTF-8 (skipping a UTF-8 BOM if present) and writes rows directly to an XLSX file without loading
     * everything into memory. Values are written as-is; any prior CSV sanitization (e.g., formula neutralization) is preserved.
     *
     * @param string      $csvFilePath full path to the source CSV file
     * @param string|null $xlsxPath    Optional explicit output path; defaults to the CSV path with ".xlsx" extension.
     * @param string      $delimiter   CSV delimiter character used by fgetcsv
     * @param string      $enclosure   CSV enclosure character used by fgetcsv
     * @param string      $escape      CSV escape character used by fgetcsv
     *
     * @return string path to the generated XLSX file
     *
     * @throws \RuntimeException when the CSV is missing or cannot be opened
     */
    private static function convertCsvToXlsx(
        string $csvFilePath,
        string $xlsxPath = null,
        string $delimiter = ',',
        string $enclosure = '"',
        string $escape = '\\'
    ): string {
        if (!is_file($csvFilePath) || !is_readable($csvFilePath)) {
            throw new \RuntimeException(sprintf('CSV "%s" not readable.', $csvFilePath));
        }

        $xlsxPath = $xlsxPath ?: preg_replace('/\.csv$/i', '.xlsx', $csvFilePath) ?: ($csvFilePath.'.xlsx');

        $in = @fopen($csvFilePath, 'r'); // Windows-safe
        if (false === $in) {
            throw new \RuntimeException(sprintf('Unable to open CSV "%s".', $csvFilePath));
        }

        $writer = WriterEntityFactory::createXLSXWriter();
        // optional if you want to control tmp dir:
        // $writer->setTempFolder(sys_get_temp_dir());
        $writer->openToFile($xlsxPath);

        // If you wrote a UTF-8 BOM into the CSV for Excel, skip it if present.
        $peek = fgets($in, 4);
        if (false !== $peek) {
            $bom = "\xEF\xBB\xBF";
            if (0 === strncmp($peek, $bom, 3)) {
                // already consumed BOM bytes in $peek; nothing else to do
            } else {
                // push back what we read since no BOM
                fseek($in, 0);
            }
        }

        while (($row = fgetcsv($in, 0, $delimiter, $enclosure, $escape)) !== false) {
            // Keep values as-is; OpenSpout will write them as text/numbers appropriately.
            $writer->addRow(WriterEntityFactory::createRowFromArray($row));
        }

        fclose($in);
        $writer->close();

        return $xlsxPath;
    }
}
