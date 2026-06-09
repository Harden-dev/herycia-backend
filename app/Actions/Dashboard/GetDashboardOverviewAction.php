<?php

namespace App\Actions\Dashboard;

use App\Data\Dashboard\DashboardMetricData;
use App\Data\Dashboard\DashboardOverviewData;
use App\Repositories\Contracts\DashboardRepositoryInterface;
use App\Repositories\Contracts\QueueEntryRepositoryInterface;
use App\Services\Dashboard\DashboardStatsService;
use App\Services\Salon\SalonContextService;
use Carbon\Carbon;

class GetDashboardOverviewAction
{
    public function __construct(
        private SalonContextService $salonContext,
        private DashboardRepositoryInterface $dashboardRepository,
        private QueueEntryRepositoryInterface $queueEntryRepository,
        private DashboardStatsService $dashboardStatsService,
    ) {}

    public function execute(?string $date = null): DashboardOverviewData
    {
        $salon = $this->salonContext->resolveAuthenticatedSalon();
        $reference = Carbon::parse($date ?? now()->toDateString());
        $previous = $reference->copy()->subDay();

        $appointmentsToday = $this->dashboardRepository->countAppointmentsOnDate($salon->id, $reference);
        $appointmentsYesterday = $this->dashboardRepository->countAppointmentsOnDate($salon->id, $previous);

        $revenueToday = $this->dashboardRepository->sumPaidRevenueOnDate($salon->id, $reference);
        $revenueYesterday = $this->dashboardRepository->sumPaidRevenueOnDate($salon->id, $previous);

        $activeSince = $reference->copy()->subDays(30)->startOfDay();
        $previousActiveFrom = $reference->copy()->subDays(60)->startOfDay();
        $previousActiveTo = $reference->copy()->subDays(30)->startOfDay();

        $activeClients = $this->dashboardRepository->countActiveClientsSince($salon->id, $activeSince);
        $previousActiveClients = $this->dashboardRepository->countActiveClientsInRange(
            $salon->id,
            $previousActiveFrom,
            $previousActiveTo,
        );

        $queueWaiting = $this->queueEntryRepository->countWaitingBySalon($salon->id);

        return new DashboardOverviewData(
            appointmentsToday: $this->dashboardStatsService->metricWithTrend($appointmentsToday, $appointmentsYesterday),
            revenueToday: $this->dashboardStatsService->metricWithTrend($revenueToday, $revenueYesterday, percentOnly: true),
            activeClients: $this->dashboardStatsService->metricWithTrend(
                $activeClients,
                $previousActiveClients,
                percentOnly: true,
            ),
            queueWaiting: new DashboardMetricData(value: $queueWaiting),
        );
    }
}
