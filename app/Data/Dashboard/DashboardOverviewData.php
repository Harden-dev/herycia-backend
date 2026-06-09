<?php

namespace App\Data\Dashboard;

readonly class DashboardOverviewData
{
    public function __construct(
        public DashboardMetricData $appointmentsToday,
        public DashboardMetricData $revenueToday,
        public DashboardMetricData $activeClients,
        public DashboardMetricData $queueWaiting,
    ) {}
}
