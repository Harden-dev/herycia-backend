<?php

namespace App\Actions\Admin;

use App\Data\Admin\AdminStatsOverviewData;
use App\Repositories\Contracts\AdminStatsRepositoryInterface;

class AdminStatsAction
{
    public function __construct(
        private AdminStatsRepositoryInterface $adminStatsRepository,
    ) {}

    public function overview(): AdminStatsOverviewData
    {
        return new AdminStatsOverviewData(
            salonsCount: $this->adminStatsRepository->countSalons(),
            usersCount: $this->adminStatsRepository->countUsers(),
            mrr: $this->adminStatsRepository->calculateMrr(),
            newSalonsThisMonth: $this->adminStatsRepository->countNewSalonsThisMonth(),
            expiredSubscriptionsCount: $this->adminStatsRepository->countExpiredSubscriptions(),
            appointmentsCount: $this->adminStatsRepository->countAppointments(),
            recentBillingPayments: $this->adminStatsRepository->getRecentBillingPayments(),
        );
    }

    /** @return array<string, int> */
    public function salonsByCity(): array
    {
        return $this->adminStatsRepository->countSalonsByCity();
    }
}
