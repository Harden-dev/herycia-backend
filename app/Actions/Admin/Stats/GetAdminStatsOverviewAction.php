<?php

namespace App\Actions\Admin\Stats;

use App\Data\Admin\AdminStatsOverviewData;
use App\Repositories\Contracts\AdminStatsRepositoryInterface;

class GetAdminStatsOverviewAction
{
    public function __construct(
        private AdminStatsRepositoryInterface $adminStatsRepository,
    ) {}

    public function execute(): AdminStatsOverviewData
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
}
