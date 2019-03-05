<?php

namespace EWZ\SymfonyAdminBundle\Report;

use Doctrine\Common\Persistence\ObjectManager;
use EWZ\SymfonyAdminBundle\Repository\AbstractRepository;
use Pagerfanta\Pagerfanta;

abstract class AbstractReport
{
    /** @var ObjectManager */
    protected $objectManager;

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
        if (!$this->getChartLabels() && $result = $this->getChartMinMaxDates()) {
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

            if (!$this->getChartLabels()) {
                foreach ($items as $label => $item) {
                    // skip if out of date range
                    if (new \DateTime($row[$this->getChartGroupByField()]) < new \DateTime($item['start'])
                        || new \DateTime($row[$this->getChartGroupByField()]) >= new \DateTime($item['end'])
                    ) {
                        continue;
                    }

                    foreach (array_keys($this->getChartColumns()) as $key) {
                        $items[$label]['data'][$key] += $this->getChartConvertCallback()($this->calcComplexColumn($key, $row));
                    }
                }
            } else {
                foreach (array_keys($this->getChartColumns()) as $key) {
                    if (!isset($items[$key]['data'][$row[$this->getChartGroupByField()]])) {
                        $items[$key]['data'][$row[$this->getChartGroupByField()]] = 0;
                    }

                    $items[$key]['data'][$row[$this->getChartGroupByField()]] += $this->getChartConvertCallback()($this->calcComplexColumn($key, $row));
                }
            }

            foreach (array_keys($this->getChartTotals()) as $key) {
                $totals[$key] += $this->getChartConvertCallback()($this->calcComplexColumn($key, $row));
            }
        }

        if ($labels = $this->getChartLabels()) {
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
            foreach (array_values($this->getChartTotals()) as $value) {
                $tmp[$value] = [
                    'name' => $value,
                    'data' => [],
                ];
            }

            foreach ($items as $item) {
                foreach ($this->getChartTotals() as $key => $value) {
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
            return $this->getRepository()->getGroupedData($this->getCriteria(), -1, null, null, $groupBy);
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
                // get next money
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
     * @param string $column
     * @param array  $data
     *
     * @return float
     */
    private function calcComplexColumn($column, $data): float
    {
        $value = $data[$column] ?? 0;

        $complexColumns = $this->getChartComplexColumns();
        if (array_key_exists($column, $complexColumns)) {
            $formula = $complexColumns[$column];

            foreach ($data as $key => $value) {
                $formula = str_replace($key, floatval($value), $formula);
            }

            $value = 0 + create_function('', sprintf('return (%s);', $formula))();
        }

        return $value;
    }
}
