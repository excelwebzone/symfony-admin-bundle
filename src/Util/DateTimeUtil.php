<?php

namespace EWZ\SymfonyAdminBundle\Util;

final class DateTimeUtil
{
    /**
     * @param string $unit
     *
     * @return [\DateTimeInterface, \DateTimeInterface]|null
     */
    public static function getDateRange(string $unit): ?array
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
     * @param string             $unit
     * @param \DateTimeInterface $from
     * @param \DateTimeInterface $to
     *
     * @return array
     */
    public static function getDatePeriodItems(string $unit, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $from->setTime(0, 0, 0);
        $to->setTime(0, 0, 0);

        // get next day (covert max date)
        $to->modify('next day');

        $items = [];
        switch ($unit) {
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
}
