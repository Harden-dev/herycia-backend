<?php

namespace App\Services\Dashboard;

use App\Data\Dashboard\ChartPointData;
use App\Data\Dashboard\DashboardChartStatsData;
use App\Data\Dashboard\DashboardMetricData;
use App\Repositories\Contracts\DashboardRepositoryInterface;
use Carbon\Carbon;

class DashboardStatsService
{
    /** @var list<string> */
    private const MONTH_LABELS = [
        1 => 'Jan', 2 => 'Fév', 3 => 'Mar', 4 => 'Avr', 5 => 'Mai', 6 => 'Jun',
        7 => 'Jul', 8 => 'Aoû', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Déc',
    ];

    /** @var list<string> */
    private const DAY_LABELS = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];

    public function __construct(
        private DashboardRepositoryInterface $dashboardRepository,
    ) {}

    public function buildClientsStats(string $salonId, string $period, Carbon $reference): DashboardChartStatsData
    {
        if ($period === 'month') {
            $year = $reference->year;
            $counts = $this->dashboardRepository->countNewClientsByMonth($salonId, $year);
            $points = [];
            foreach ($counts as $month => $count) {
                $points[] = new ChartPointData(self::MONTH_LABELS[$month], $count);
            }
            $total = array_sum($counts);
            $previousTotal = array_sum($this->dashboardRepository->countNewClientsByMonth($salonId, $year - 1));

            return new DashboardChartStatsData(
                period: 'month',
                points: $points,
                total: $total,
                trendPercent: $this->percentChange($total, $previousTotal),
            );
        }

        $toYear = $reference->year;
        $fromYear = $toYear - 4;
        $counts = $this->dashboardRepository->countNewClientsByYear($salonId, $fromYear, $toYear);
        $points = [];
        foreach ($counts as $year => $count) {
            $points[] = new ChartPointData((string) $year, $count);
        }
        $total = array_sum($counts);
        $previousCounts = $this->dashboardRepository->countNewClientsByYear($salonId, $fromYear - 5, $fromYear - 1);
        $previousTotal = array_sum($previousCounts);

        return new DashboardChartStatsData(
            period: 'year',
            points: $points,
            total: $total,
            trendPercent: $this->percentChange($total, $previousTotal),
        );
    }

    public function buildRevenueStats(string $salonId, string $period, Carbon $reference): DashboardChartStatsData
    {
        return match ($period) {
            'day' => $this->buildDailyRevenueStats($salonId, $reference),
            'week' => $this->buildWeeklyRevenueStats($salonId, $reference),
            'month' => $this->buildMonthlyRevenueStats($salonId, $reference),
            'year' => $this->buildYearlyRevenueStats($salonId, $reference),
            default => throw new \InvalidArgumentException("Période invalide : {$period}"),
        };
    }

    public function metricWithTrend(int $current, int $previous, bool $percentOnly = false): DashboardMetricData
    {
        if ($percentOnly) {
            return new DashboardMetricData(
                value: $current,
                trend: null,
                trendPercent: $this->percentChange($current, $previous),
            );
        }

        $delta = $current - $previous;

        return new DashboardMetricData(
            value: $current,
            trend: $delta !== 0 ? $delta : null,
            trendPercent: null,
        );
    }

    private function buildDailyRevenueStats(string $salonId, Carbon $reference): DashboardChartStatsData
    {
        $start = $reference->copy()->startOfWeek(Carbon::MONDAY);
        $points = [];
        $total = 0;

        for ($i = 0; $i < 7; $i++) {
            $day = $start->copy()->addDays($i);
            $amount = $this->dashboardRepository->sumPaidRevenueOnDate($salonId, $day);
            $points[] = new ChartPointData(self::DAY_LABELS[$i], $amount);
            $total += $amount;
        }

        $previousStart = $start->copy()->subWeek();
        $previousTotal = 0;
        for ($i = 0; $i < 7; $i++) {
            $previousTotal += $this->dashboardRepository->sumPaidRevenueOnDate(
                $salonId,
                $previousStart->copy()->addDays($i),
            );
        }

        return new DashboardChartStatsData(
            period: 'day',
            points: $points,
            total: $total,
            trendPercent: $this->percentChange($total, $previousTotal),
        );
    }

    private function buildWeeklyRevenueStats(string $salonId, Carbon $reference): DashboardChartStatsData
    {
        $monthStart = $reference->copy()->startOfMonth();
        $monthEnd = $reference->copy()->endOfMonth();
        $points = [];
        $total = 0;
        $weekIndex = 1;
        $cursor = $monthStart->copy();

        while ($cursor->lte($monthEnd)) {
            $weekEnd = $cursor->copy()->endOfWeek(Carbon::SUNDAY);
            if ($weekEnd->gt($monthEnd)) {
                $weekEnd = $monthEnd->copy();
            }

            $amount = $this->dashboardRepository->sumPaidRevenueBetween($salonId, $cursor, $weekEnd);
            $points[] = new ChartPointData('S'.$weekIndex, $amount);
            $total += $amount;
            $weekIndex++;
            $cursor = $weekEnd->copy()->addDay()->startOfDay();

            if ($weekIndex > 6) {
                break;
            }
        }

        $previousMonthStart = $monthStart->copy()->subMonth()->startOfMonth();
        $previousMonthEnd = $monthStart->copy()->subMonth()->endOfMonth();
        $previousTotal = $this->dashboardRepository->sumPaidRevenueBetween(
            $salonId,
            $previousMonthStart,
            $previousMonthEnd,
        );

        return new DashboardChartStatsData(
            period: 'week',
            points: $points,
            total: $total,
            trendPercent: $this->percentChange($total, $previousTotal),
        );
    }

    private function buildMonthlyRevenueStats(string $salonId, Carbon $reference): DashboardChartStatsData
    {
        $year = $reference->year;
        $points = [];
        $total = 0;

        for ($month = 1; $month <= 12; $month++) {
            $from = Carbon::create($year, $month, 1)->startOfMonth();
            $to = $from->copy()->endOfMonth();
            $amount = $this->dashboardRepository->sumPaidRevenueBetween($salonId, $from, $to);
            $points[] = new ChartPointData(self::MONTH_LABELS[$month], $amount);
            $total += $amount;
        }

        $previousTotal = 0;
        for ($month = 1; $month <= 12; $month++) {
            $from = Carbon::create($year - 1, $month, 1)->startOfMonth();
            $to = $from->copy()->endOfMonth();
            $previousTotal += $this->dashboardRepository->sumPaidRevenueBetween($salonId, $from, $to);
        }

        return new DashboardChartStatsData(
            period: 'month',
            points: $points,
            total: $total,
            trendPercent: $this->percentChange($total, $previousTotal),
        );
    }

    private function buildYearlyRevenueStats(string $salonId, Carbon $reference): DashboardChartStatsData
    {
        $toYear = $reference->year;
        $fromYear = $toYear - 4;
        $points = [];
        $total = 0;

        for ($year = $fromYear; $year <= $toYear; $year++) {
            $from = Carbon::create($year, 1, 1)->startOfYear();
            $to = $from->copy()->endOfYear();
            $amount = $this->dashboardRepository->sumPaidRevenueBetween($salonId, $from, $to);
            $points[] = new ChartPointData((string) $year, $amount);
            $total += $amount;
        }

        $previousTotal = 0;
        for ($year = $fromYear - 5; $year <= $fromYear - 1; $year++) {
            $from = Carbon::create($year, 1, 1)->startOfYear();
            $to = $from->copy()->endOfYear();
            $previousTotal += $this->dashboardRepository->sumPaidRevenueBetween($salonId, $from, $to);
        }

        return new DashboardChartStatsData(
            period: 'year',
            points: $points,
            total: $total,
            trendPercent: $this->percentChange($total, $previousTotal),
        );
    }

    private function percentChange(int $current, int $previous): ?float
    {
        if ($previous === 0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
