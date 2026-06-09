<?php

namespace App\Data\Admin;

use Illuminate\Database\Eloquent\Collection;

readonly class AdminStatsOverviewData
{
    public function __construct(
        public int $salonsCount,
        public int $usersCount,
        public int $mrr,
        public int $newSalonsThisMonth,
        public int $expiredSubscriptionsCount,
        public int $appointmentsCount,
        public Collection $recentBillingPayments,
    ) {}
}
