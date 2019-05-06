<?php

namespace EWZ\SymfonyAdminBundle\Report;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Persistence\ObjectManager;
use EWZ\SymfonyAdminBundle\Model\User;
use EWZ\SymfonyAdminBundle\Repository\AbstractRepository;
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
            $items = $this->getDatePeriodItems(
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

        foreach ($this->getChartData() as $row) {
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

                    foreach (array_keys($this->getChartColumns()) as $key) {
                        $items[$label]['data'][$key] += $this->getChartConvertCallback()($this->calcComplexColumn($key, $row, $this->getChartComplexColumns()));
                    }
                }
            } else {
                foreach (array_keys($this->getChartColumns()) as $key) {
                    if (!isset($items[$key]['data'][$row[$this->getChartGroupByField()]])) {
                        $items[$key]['data'][$row[$this->getChartGroupByField()]] = 0;
                    }

                    $items[$key]['data'][$row[$this->getChartGroupByField()]] += $this->getChartConvertCallback()($this->calcComplexColumn($key, $row, $this->getChartComplexColumns()));
                }
            }

            foreach (array_keys($this->getChartTotals()) as $key) {
                $totals[$key] += $this->getChartConvertCallback()($this->calcComplexColumn($key, $row, $this->getChartComplexColumns()));
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
            $items = array_values($items);
            foreach ($items as $key => $item) {
                foreach (array_keys($labels) as $label) {
                    if (!isset($item['data'][$label])) {
                        $items[$key]['data'][$label] = 0;
                    }
                }
                ksort($items[$key]['data']);
                $items[$key]['data'] = array_values($items[$key]['data']);
            }

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
     * @return array
     */
    public function getChartData(): array
    {
        if ($this->getRepository() && $groupBy = $this->getChartGroupByField()) {
            return $this->getRepository()
                ->getGroupedData($this->getCriteria(), -1, null, null, $groupBy)
                ->getCurrentPageResults();
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
        return 'intval';
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

                if (!in_array($options['format'], ['text', 'enum'])) {
                    $value = $this->calcComplexColumn($column, $item, $this->getExportComplexColumns());
                }

                // hide value / reset to null
                $hide = $options['options']['hide'] ?? false;
                if (true === $hide || ($hide instanceof \Closure && $hide($item))) {
                    $value = null;
                }

                switch ($options['format']) {
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
                            $value = $value
                                ->setTimezone(new \DateTimeZone($this->getUser()->getTimezone()))
                                ->format(sprintf('%s H:i:s', $this->getUser()->getDateFormat()));
                        }

                        break;
                }

                if ($value instanceof \DateTimeInterface) {
                    $value = $value
                        ->setTimezone(new \DateTimeZone($this->getUser()->getTimezone()))
                        ->format($this->getUser()->getDateFormat());
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
     * $format = text, money, percent, or enum
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
     *
     * $format = number, money, or percent
     *
     * @return array
     */
    public function createTotalColumn(string $column, string $format = 'number'): array
    {
        return [
            'column' => $column,
            'format' => $format,
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
            if ($this->getRepository()) {
                $adapter = $items->getAdapter();
                if (method_exists($adapter, 'getQuery')) {
                    $columns = $this->getTotalColumns();
                    foreach ($columns as &$column) {
                        $column = $column['column'];
                    }

                    $items = $this->getRepository()->getSearchTotals($columns, $adapter->getQuery());
                } else {
                    $items = $items->getCurrentPageResults();
                }
            }
        }

        $columns = [];

        if (is_array($items)) {
            foreach ($items as $item) {
                foreach ($item as $key => $value) {
                    if (!array_key_exists($key, $columns)) {
                        $columns[$key] = 0;
                    }

                    $columns[$key] += $value;
                }
            }
        }

        foreach ($this->getTotalColumns() as $key => $value) {
            if (array_key_exists($key, $columns)) {
                $columns[$key] = number_format($columns[$key], 2);

                switch ($value['format']) {
                    case 'money':
                        $columns[$key] = sprintf('$%s', $columns[$key]);
                        break;

                    case 'percent':
                        $columns[$key] = sprintf('%s%%', $columns[$key]);
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
     * @param \DateTimeInterface $from
     * @param \DateTimeInterface $to
     *
     * @return array
     */
    protected function getDatePeriodItems(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $from->setTime(0, 0, 0);
        $to->setTime(0, 0, 0);

        // get next day (covert max date)
        $to->modify('next day');

        $items = [];
        switch ($this->getGroupingType()) {
            case 'daily':
                $period = new \DatePeriod($from, new \DateInterval('P1D'), $to);
                foreach ($period as $day) {
                    $start = $day;
                    $end = (clone $start)->modify('next day');

                    $month = $start->format('M');
                    $day = $start->format('d');

                    $items[sprintf('%s %d', $month, $day)] = [
                        'start' => $start->format('Y-m-d'),
                        'end' => $end->format('Y-m-d'),
                    ];
                }

                break;

            case 'weekly':
                if (1 < $to->format('N')) {
                    $to->modify('next monday next day');
                }

                $period = new \DatePeriod($from, new \DateInterval('P1W'), $to);
                foreach ($period as $day) {
                    $start = $day->modify('last monday');
                    $end = (clone $start)->modify('next monday');

                    $month = $start->format('M');
                    $day = $start->format('d');

                    $items[sprintf('%s %d', $month, $day)] = [
                        'start' => $start->format('Y-m-d'),
                        'end' => $end->format('Y-m-d'),
                    ];
                }

                break;

            case 'quarterly':
                $month = $from->format('n');
                if ($month < 4) {
                    $from->modify('first day of january');
                } elseif ($month > 3 && $month < 7) {
                    $from->modify('first day of april');
                } elseif ($month > 6 && $month < 10) {
                    $from->modify('first day of july');
                } elseif ($month > 9) {
                    $from->modify('first day of october');
                }

                $month = $to->format('n');
                if ($month < 4) {
                    $to->modify('last day of march');
                } elseif ($month > 3 && $month < 7) {
                    $to->modify('last day of june');
                } elseif ($month > 6 && $month < 10) {
                    $to->modify('last day of september');
                } elseif ($month > 9) {
                    $to->modify('last day of december');
                }
                $to->modify('next day');

                $period = new \DatePeriod($from, new \DateInterval('P3M'), $to);
                foreach ($period as $day) {
                    $start = $day->modify('first day of this month');
                    $end = (clone $start)->add(new \DateInterval('P3M'))->modify('first day of this month');

                    $month = $start->format('n');
                    $year = $start->format('y');
                    if ($month < 4) {
                        $quarter = sprintf('Q1 %d', $year);
                    } elseif ($month > 3 && $month < 7) {
                        $quarter = sprintf('Q2 %d', $year);
                    } elseif ($month > 6 && $month < 10) {
                        $quarter = sprintf('Q3 %d', $year);
                    } elseif ($month > 9) {
                        $quarter = sprintf('Q4 %d', $year);
                    }

                    $items[$quarter] = [
                        'start' => $start->format('Y-m-d'),
                        'end' => $end->format('Y-m-d'),
                    ];
                }

                break;

            case 'monthly':
            default:
                $period = new \DatePeriod($from, new \DateInterval('P1M'), $to);
                foreach ($period as $day) {
                    $start = $day->modify('first day of this month');
                    $end = (clone $start)->modify('first day of next month');

                    $month = $start->format('M');
                    $year = $start->format('y');

                    $items[sprintf('%s %d', $month, $year)] = [
                        'start' => $start->format('Y-m-d'),
                        'end' => $end->format('Y-m-d'),
                    ];
                }
        }

        return $items;
    }

    /**
     * @see \EWZ\SymfonyAdminBundle\Repository\AbstractRepository::getDateRange
     *
     * @param string $unit
     *
     * @return [\DateTimeInterface, \DateTimeInterface]|null
     */
    protected function getDateRange(string $unit): ?array
    {
        switch ($unit) {
            case 'today':
                $from = (new \DateTime())->setTime(0, 0, 0);
                $to = (new \DateTime('tomorrow'))->setTime(0, 0, 0);

                return [$from, $to];

            case 'yesterday':
                $from = (new \DateTime('yesterday'))->setTime(0, 0, 0);
                $to = (new \DateTime())->setTime(0, 0, 0);

                return [$from, $to];

            case 'last_week':
                $from = (new \DateTime('previous week'))->setTime(0, 0, 0);
                $to = (new \DateTime('this week'))->setTime(0, 0, 0);

                return [$from, $to];

            case 'this_week':
                $from = (new \DateTime('this week'))->setTime(0, 0, 0);
                $to = (new \DateTime('next week'))->setTime(0, 0, 0);

                return [$from, $to];

            case 'last_month':
                $from = (new \DateTime('first day of previous month'))->setTime(0, 0, 0);
                $to = (new \DateTime('first day of this month'))->setTime(0, 0, 0);

                return [$from, $to];

            case 'this_month':
                $from = (new \DateTime('first day of this month'))->setTime(0, 0, 0);
                $to = (new \DateTime('first day of next month'))->setTime(0, 0, 0);

                return [$from, $to];

            case 'last_quarter':
                $from = (new \DateTime())->setTime(0, 0, 0);
                $to = (clone $from);

                $month = $from->format('n');
                if ($month < 4) {
                    $from->modify('first day of last year october');
                } elseif ($month > 3 && $month < 7) {
                    $from->modify('first day of january');
                } elseif ($month > 6 && $month < 10) {
                    $from->modify('first day of april');
                } elseif ($month > 9) {
                    $from->modify('first day of july');
                }

                if ($month < 4) {
                    $to->modify('last day of last year december');
                } elseif ($month > 3 && $month < 7) {
                    $to->modify('last day of march');
                } elseif ($month > 6 && $month < 10) {
                    $to->modify('last day of june');
                } elseif ($month > 9) {
                    $to->modify('last day of september');
                }
                $to->modify('next day');

                return [$from, $to];

            case 'this_quarter':
                $from = (new \DateTime())->setTime(0, 0, 0);
                $to = (clone $from);

                $month = $from->format('n');
                if ($month < 4) {
                    $from->modify('first day of january');
                } elseif ($month > 3 && $month < 7) {
                    $from->modify('first day of april');
                } elseif ($month > 6 && $month < 10) {
                    $from->modify('first day of july');
                } elseif ($month > 9) {
                    $from->modify('first day of october');
                }

                if ($month < 4) {
                    $to->modify('last day of march');
                } elseif ($month > 3 && $month < 7) {
                    $to->modify('last day of june');
                } elseif ($month > 6 && $month < 10) {
                    $to->modify('last day of september');
                } elseif ($month > 9) {
                    $to->modify('last day of december');
                }
                $to->modify('next day');

                return [$from, $to];

            case 'last_year':
                $from = (new \DateTime('january first day of previous year'))->setTime(0, 0, 0);
                $to = (new \DateTime('january first day of this year'))->setTime(0, 0, 0);

                return [$from, $to];

            case 'this_year':
                $from = (new \DateTime('january first day of this year'))->setTime(0, 0, 0);
                $to = (new \DateTime('january first day of next year'))->setTime(0, 0, 0);

                return [$from, $to];

            case 'last_7_days':
            case 'last_14_days':
            case 'last_30_days':
            case 'last_45_days':
            case 'last_60_days':
            case 'last_90_days':
            case 'last_180_days':
                $split = explode('_', $unit);

                $from = (new \DateTime(sprintf('-%d day', $split[1])))->setTime(0, 0, 0);
                $to = (new \DateTime())->setTime(0, 0, 0);

                return [$from, $to];
        }

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
                $formula = str_replace($key, floatval($value), $formula);
            }

            $value = 0 + create_function('', sprintf('return (%s);', $formula))();
        }

        return floatval($value);
    }
}
