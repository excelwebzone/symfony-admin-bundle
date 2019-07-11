<?php

namespace EWZ\SymfonyAdminBundle\Report;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Persistence\ObjectManager;
use EWZ\SymfonyAdminBundle\Model\User;
use EWZ\SymfonyAdminBundle\Repository\AbstractRepository;
use EWZ\SymfonyAdminBundle\Util\DateTimeUtil;
use EWZ\SymfonyAdminBundle\Util\StringUtil;
use Pagerfanta\Pagerfanta;

abstract class AbstractReport
{
    /** @var ObjectManager */
    protected $objectManager;

    /** @var User */
    protected $user;

    /** @var array */
    protected $criteria;

    /** @var int */
    protected $page = 1;

    /** @var int */
    protected $limit;

    /** @var string */
    protected $sort;

    /** @var string */
    protected $groupingType = 'monthly';

    /**
     * @param ObjectManager $objectManager
     */
    public function __construct(ObjectManager $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * @return array [array $totals, array $items, array $labels]
     */
    public function chart(): array
    {
        // holds the overall total
        $totals = [];
        foreach (array_keys($this->getChartTotals()) as $key) {
            $totals[$key] = 0;
        }

        $items = $this->getChartColumns();
        foreach ($items as $key => $value) {
            $items[$key] = [
                'name' => $value,
                'data' => [],
            ];
        }

        $labels = $this->getChartLabels();
        if (empty($labels) && $result = $this->getChartMinMaxDates()) {
            $items = DateTimeUtil::getDatePeriodItems(
                $this->getGroupingType(),
                new \DateTime($result['min']),
                new \DateTime($result['max'])
            );
            foreach ($items as $label => $item) {
                $items[$label]['data'] = [];
                foreach (array_keys($this->getChartColumns()) as $key) {
                    $items[$label]['data'][$key] = 0;
                }
            }
        }

        // add hidden fields
        foreach ($this->getChartComplexColumns() as $key => $value) {
            preg_match_all('/\b([a-zA-Z_]+)\b/', $value, $matches);

            foreach ($matches[0] as $column) {
                if (isset($totals[$key]) && !isset($totals[$column])) {
                    $totals[$column] = 0;
                }

                if (empty($labels)) {
                    foreach ($items as $label => $item) {
                        if (isset($item['data'][$key]) && !isset($item['data'][$column])) {
                            $items[$label]['data'][$column] = 0;
                        }
                    }
                }
            }
        }

        /** @var Pagerfanta|array $result */
        $result = $this->getChartData();
        if ($result instanceof Pagerfanta) {
            $result = $result->getCurrentPageResults();
        }

        foreach ($result as $row) {
            if (!$this->getChartGroupByField()) {
                break;
            }

            if (empty($labels)) {
                foreach ($items as $label => $item) {
                    // skip if out of date range
                    if (new \DateTime($row[$this->getChartGroupByField()]) < new \DateTime($item['start'])
                        || new \DateTime($row[$this->getChartGroupByField()]) >= new \DateTime($item['end'])
                    ) {
                        continue;
                    }

                    foreach (array_keys($item['data']) as $key) {
                        if (!array_key_exists($key, $this->getChartComplexColumns())) {
                            $items[$label]['data'][$key] += $this->getChartConvertCallback()($this->getColumnValue($key, $row));
                        }
                    }
                }
            } else {
                foreach (array_keys($items) as $key) {
                    if (!isset($items[$key]['data'][$row[$this->getChartGroupByField()]])) {
                        $items[$key]['data'][$row[$this->getChartGroupByField()]] = 0;
                    }

                    $items[$key]['data'][$row[$this->getChartGroupByField()]] += $this->getChartConvertCallback()($this->calcComplexColumn($key, $row, $this->getChartComplexColumns()));
                }
            }

            foreach (array_keys($totals) as $key) {
                if (!array_key_exists($key, $this->getChartComplexColumns())) {
                    $totals[$key] += $this->getChartConvertCallback()($this->getColumnValue($key, $row));
                }
            }
        }

        if (empty($labels)) {
            foreach ($items as $label => $item) {
                foreach (array_keys($item['data']) as $key) {
                    if (array_key_exists($key, $this->getChartComplexColumns())) {
                        $items[$label]['data'][$key] = $this->getChartConvertCallback()($this->calcComplexColumn($key, $item['data'], $this->getChartComplexColumns()));
                    }
                }

                // remove extra columns
                foreach (array_keys($item['data']) as $key) {
                    if (!array_key_exists($key, $this->getChartColumns())) {
                        unset($items[$label]['data'][$key]);
                    }
                }
            }
        }

        foreach ($totals as $key => $value) {
            if (array_key_exists($key, $this->getChartComplexColumns())) {
                $totals[$key] = $this->getChartConvertCallback()($this->calcComplexColumn($key, $totals, $this->getChartComplexColumns()));
            }
        }
        // remove extra columns
        foreach ($totals as $key => $value) {
            if (!array_key_exists($key, $this->getChartTotals())) {
                unset($totals[$key]);
            }
        }

        if (!empty($labels)) {
            // remove empty labels
            foreach (array_keys($labels) as $label) {
                $total = 0;
                foreach ($items as $item) {
                    $labels[$label][] = $item['data'][$label] ?? 0;
                    $total += $item['data'][$label] ?? 0;
                }

                if (0 === $total) {
                    unset($labels[$label]);
                }
            }

            // prepare data
            $tmp = [];
            foreach ($items as $key => $item) {
                $tmp[$key] = [
                    'name' => $item['name'],
                    'data' => [],
                ];

                foreach (array_keys($labels) as $label) {
                    $tmp[$key]['data'][] = $item['data'][$label] ?? 0;
                }
            }

            $items = array_values($tmp);

            // set labels
            $labels = array_values(array_map(function ($value) {
                return $value[0];
            }, $labels));
        } else {
            // prepare data
            $tmp = [];
            foreach (array_values($this->getChartColumns()) as $value) {
                $tmp[$value] = [
                    'name' => $value,
                    'data' => [],
                ];
            }

            foreach ($items as $item) {
                foreach ($this->getChartColumns() as $key => $value) {
                    $tmp[$value]['data'][] = $item['data'][$key] ?? 0;
                }
            }

            $labels = array_keys($items);
            $items = array_values($tmp);
        }

        return [$totals, $items, $labels];
    }

    /**
     * @return array|null [min, max]
     */
    public function getChartMinMaxDates(): ?array
    {
        if ($this->getRepository() && $groupBy = $this->getChartGroupByField()) {
            return $this->getRepository()->getMinMax($this->getCriteria(), $groupBy);
        }

        return null;
    }

    /**
     * @return array|null
     */
    public function getChartLabels(): ?array
    {
        return null;
    }

    /**
     * @return array
     */
    public function getChartComplexColumns(): array
    {
        return [];
    }

    /**
     * @return array
     */
    public function getChartColumns(): array
    {
        return ['total' => 'Total'];
    }

    /**
     * @return array
     */
    public function getChartTotals(): array
    {
        return ['total' => 'Total'];
    }

    /**
     * @return Pagerfanta|array
     */
    public function getChartData()
    {
        if ($this->getRepository() && $groupBy = $this->getChartGroupByField()) {
            return $this->getRepository()->getAllGroupedData($this->getCriteria(), null, $groupBy);
        }

        return [];
    }

    /**
     * @return string|null
     */
    public function getChartGroupByField(): ?string
    {
        if ($this->getRepository() && in_array('createdAt', $this->getRepository()->getFieldNames())) {
            return 'createdAt';
        }

        return null;
    }

    /**
     * @return string
     */
    public function getChartConvertCallback(): string
    {
        return 'floatval';
    }

    /**
     * @return array
     */
    public function export(): array
    {
        if (!$this->getRepository() || 0 === count($this->getExportColumns())) {
            return [];
        }

        $data = [];

        // add header
        $data[0] = [];
        foreach (array_values($this->getExportColumns()) as $options) {
            $data[0][] = $options['label'];
        }

        // force all records
        $this->setPage(-1);

        // add rows
        foreach ($this->search() as $item) {
            $orgItem = $item;
            $row = [];
            foreach ($this->getExportColumns() as $column => $options) {
                $item = $orgItem;

                // handle sub-columns
                if (false !== strpos($column, '.')) {
                    $split = explode('.', $column);

                    // @hack: handle multi column with same field
                    // usually used to show value in different column
                    // based on "hide" rules
                    if (is_numeric($split[0])) {
                        array_shift($split);
                    }

                    for ($i = 0; $i < count($split) - 1; ++$i) {
                        $parentColumn = $split[$i];
                        $item = $this->getColumnValue($parentColumn, $item);

                        // skip empty parent
                        if (!is_object($item)
                            || ($item instanceof Collection
                                && 0 === $item->count()
                            )
                        ) {
                            continue 2;
                        }
                    }

                    $column = end($split);
                }

                $value = $this->getColumnValue($column, $item);

                if (!in_array($options['format'], ['text', 'enum', 'datetime', 'serialize'])) {
                    $value = $this->calcComplexColumn($column, $item, $this->getExportComplexColumns());
                }

                // hide value / reset to null
                $hide = $options['options']['hide'] ?? false;
                if (true === $hide || ($hide instanceof \Closure && $hide($orgItem))) {
                    $value = null;
                }

                switch ($options['format']) {
                    case 'number':
                        $value = number_format($value, 2);
                        break;

                    case 'money':
                        $value = sprintf('$%s', number_format($value, 2));
                        break;

                    case 'percent':
                        $value = sprintf('%s%%', number_format($value, 2));
                        break;

                    case 'enum':
                        $enumClass = $options['options']['class'];
                        if ($value && $enumClass::isValueExist($value)) {
                            $value = $enumClass::getReadableValue($value);
                        }

                        break;

                    case 'datetime':
                        if ($value instanceof \DateTimeInterface) {
                            $value = $value->format(sprintf('%s H:i:s', $this->getUser()->getDateFormat()));
                        }

                        break;

                    case 'serialize':
                        try {
                            $tmp = $value ? unserialize($value) : [];
                            if (!is_array($tmp)) {
                                $tmp = [$tmp];
                            }

                            if ($enumClass = $options['options']['enumClass']) {
                                foreach ($tmp as $k => $v) {
                                    if ($enumClass::isValueExist($v)) {
                                        $tmp[$k] = $enumClass::getReadableValue($v);
                                    }
                                }
                            } else {
                                foreach ($tmp as $k => $v) {
                                    $tmp[$k] = StringUtil::ucwords($v);
                                }
                            }

                            $value = implode(', ', $tmp);
                        } catch (\Exception $e) {
                            // do nothing
                        }
                }

                if ($value instanceof \DateTimeInterface) {
                    $value = $value->format($this->getUser()->getDateFormat());
                } elseif (is_array($value) || $value instanceof Collection) {
                    $values = [];
                    foreach ($value as $v) {
                        $values[] = (string) $v;
                    }
                    $value = implode(', ', $values);
                } elseif (is_numeric($value)) {
                    $value = number_format($value, 2);
                } elseif (is_string($value) || is_object($value)) {
                    $value = (string) $value;
                }

                $row[] = $value;
            }

            $data[] = $row;
        }

        return $data;
    }

    /**
     * @param string     $label
     * @param string     $format
     * @param array|null $options
     *
     * $format = text, datetime, serialize, number, money, percent, or enum
     *
     * @return array
     */
    public function createExportColumn(string $label, string $format = 'text', array $options = null): array
    {
        return [
            'label' => $label,
            'format' => $format,
            'options' => $options,
        ];
    }

    /**
     * @return array
     */
    public function getExportComplexColumns(): array
    {
        return [];
    }

    /**
     * @return array
     */
    public function getExportColumns(): array
    {
        return [];
    }

    /**
     * @return Pagerfanta|array
     */
    public function search()
    {
        if ($this->getRepository()) {
            return $this->getRepository()->search(
                $this->getCriteria(),
                $this->getPage(),
                $this->getLimit(),
                $this->getSort()
            );
        }

        return [];
    }

    /**
     * @param string $column
     * @param string $format
     * @param bool   $useSum
     * @param bool   $useFormula
     *
     * $format = number, money, percent, or time
     *
     * @return array
     */
    public function createTotalColumn(string $column, string $format = 'number', bool $useSum = true, bool $useFormula = false): array
    {
        return [
            'column' => $column,
            'format' => $format,
            'useSum' => $useSum,
            'useFormula' => $useFormula,
        ];
    }

    /**
     * @return array
     */
    public function getTotalColumns(): array
    {
        return [];
    }

    /**
     * @return Pagerfanta|array|null
     */
    public function getTotalData()
    {
        return null;
    }

    /**
     * @params Pagerfanta|array $items
     *
     * @return array
     */
    public function searchTotals($items): array
    {
        if (0 === count($this->getTotalColumns())) {
            return [];
        }

        if ($items instanceof Pagerfanta) {
            $adapter = $items->getAdapter();
            if (method_exists($adapter, 'getQuery')) {
                if ($this->getRepository()) {
                    $items = $this->getRepository()->getSearchTotals($this->getTotalColumns(), $adapter->getQuery());
                }
            } else {
                $items = $items->getCurrentPageResults();
            }
        }

        $columns = [];
        if (is_array($items)) {
            foreach ($items as $item) {
                foreach ($item as $key => $value) {
                    if (!array_key_exists($key, $this->getTotalColumns())) {
                        continue;
                    }

                    if (!array_key_exists($key, $columns)) {
                        $columns[$key] = 0;
                    }

                    $columns[$key] += $value;
                }
            }
        }

        $complexColumns = [];
        foreach ($this->getTotalColumns() as $key => $value) {
            if ($value['useFormula']) {
                $complexColumns[$key] = $value['column'];
            }
        }
        foreach ($complexColumns as $key => $value) {
            $columns[$key] = $this->calcComplexColumn($key, $columns, $complexColumns);
        }

        foreach ($this->getTotalColumns() as $key => $value) {
            if (array_key_exists($key, $columns)) {
                switch ($value['format']) {
                    case 'time':
                        $hours = floor($columns[$key] / 3600);
                        $minutes = floor(($columns[$key] / 60) % 60);

                        $columns[$key] = sprintf('%02d:%02d', $hours, $minutes);
                        break;

                    case 'money':
                        $columns[$key] = sprintf('$%s', number_format($columns[$key], 2));
                        break;

                    case 'percent':
                        $columns[$key] = sprintf('%s%%', number_format($columns[$key], 2));
                        break;

                    case 'number':
                    default:
                        $columns[$key] = number_format($columns[$key], 2);
                        break;
                }
            }
        }

        return $columns;
    }

    /**
     * @return User|null
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * @param User|null $user
     */
    public function setUser(User $user = null): void
    {
        $this->user = $user;
    }

    /**
     * @return array
     */
    public function getCriteria(): array
    {
        return $this->criteria ?: [];
    }

    /**
     * @param array $criteria
     */
    public function setCriteria(array $criteria): void
    {
        $this->criteria = $criteria;
    }

    /**
     * @return int|null
     */
    public function getPage(): ?int
    {
        return $this->page;
    }

    /**
     * @param int|null $page
     */
    public function setPage(int $page = null): void
    {
        $this->page = $page;
    }

    /**
     * @return int|null
     */
    public function getLimit(): ?int
    {
        return $this->limit;
    }

    /**
     * @param int|null $limit
     */
    public function setLimit(int $limit = null): void
    {
        $this->limit = $limit;
    }

    /**
     * @return string|null
     */
    public function getSort(): ?string
    {
        return $this->sort;
    }

    /**
     * @param string|null $sort
     */
    public function setSort(string $sort = null): void
    {
        $this->sort = $sort;
    }

    /**
     * @return string|null
     */
    public function getGroupingType(): ?string
    {
        return $this->groupingType;
    }

    /**
     * @param string|null $groupingType
     */
    public function setGroupingType(string $groupingType = null): void
    {
        $this->groupingType = $groupingType;
    }

    /**
     * @return AbstractRepository|null
     */
    protected function getRepository(): ?AbstractRepository
    {
        return null;
    }

    /**
     * @param string $column
     * @param array  $data
     *
     * @return mixed
     */
    private function getColumnValue($column, $data)
    {
        $value = null;

        if (is_array($data)) {
            $value = $data[$column] ?? null;
        } else {
            $method = lcfirst(StringUtil::classify($column));
            if (!method_exists($data, $method)) {
                $method = sprintf('get%s', StringUtil::classify($column));
            }
            if (!method_exists($data, $method)) {
                $method = sprintf('is%s', StringUtil::classify($column));
            }

            $value = $data->$method();
        }

        return $value;
    }

    /**
     * @param string $column
     * @param array  $data
     * @param array  $complexColumns
     *
     * @return float
     */
    private function calcComplexColumn($column, $data, $complexColumns): float
    {
        $value = $this->getColumnValue($column, $data);

        if (array_key_exists($column, $complexColumns)) {
            $formula = $complexColumns[$column];

            foreach ($data as $key => $value) {
                $formula = preg_replace(sprintf('/\b%s\b/', $key), floatval($value), $formula);
            }

            $value = 0 + create_function('', sprintf('return (%s);', $formula))();
        }

        return floatval($value);
    }
}
