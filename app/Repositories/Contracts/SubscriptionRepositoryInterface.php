<?php

namespace App\Repositories\Contracts;

use App\Models\Subscription;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SubscriptionRepositoryInterface
{
    public function create(array $data): Subscription;

    public function findLatestBySalonId(string $salonId): ?Subscription;

    public function findCurrentBySalonId(string $salonId): ?Subscription;

    public function findById(string $id): ?Subscription;

    public function update(Subscription $subscription, array $data): Subscription;

    public function paginateForAdmin(
        int $perPage = 15,
        ?string $search = null,
        ?string $planId = null,
        ?string $status = null,
        ?string $salonId = null,
    ): LengthAwarePaginator;
}
