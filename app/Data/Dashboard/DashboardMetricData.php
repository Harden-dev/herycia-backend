<?php

namespace App\Data\Dashboard;

readonly class DashboardMetricData
{
    public function __construct(
        public int $value,
        public ?int $trend = null,
        public ?float $trendPercent = null,
    ) {}
}
