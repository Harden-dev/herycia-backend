<?php

namespace App\Data\Dashboard;

readonly class DashboardChartStatsData
{
    /** @param list<ChartPointData> $points */
    public function __construct(
        public string $period,
        public array $points,
        public int $total,
        public ?float $trendPercent = null,
    ) {}
}
