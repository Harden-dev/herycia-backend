<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface AdminStatsRepositoryInterface
{
    public function countSalons(): int;

    public function countUsers(): int;

    public function calculateMrr(): int;

    public function countNewSalonsThisMonth(): int;

    public function countExpiredSubscriptions(): int;

    public function countAppointments(): int;

    /** @return array<string, int> */
    public function countSalonsByCity(): array;

    /** @return Collection<int, \App\Models\SubscriptionPayment> */
    public function getRecentBillingPayments(int $limit = 5): Collection;
}
