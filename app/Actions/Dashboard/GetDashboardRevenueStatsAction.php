<?php

namespace App\Actions\Dashboard;

use App\Data\Dashboard\DashboardChartStatsData;
use App\Services\Dashboard\DashboardStatsService;
use App\Services\Salon\SalonContextService;
use Carbon\Carbon;

class GetDashboardRevenueStatsAction
{
    public function __construct(
        private SalonContextService $salonContext,
        private DashboardStatsService $dashboardStatsService,
    ) {}

    public function execute(string $period, ?string $date = null): DashboardChartStatsData
    {
        $salon = $this->salonContext->resolveAuthenticatedSalon();
        $reference = Carbon::parse($date ?? now()->toDateString());

        return $this->dashboardStatsService->buildRevenueStats($salon->id, $period, $reference);
    }
}
