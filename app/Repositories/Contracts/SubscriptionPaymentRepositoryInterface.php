<?php

namespace App\Repositories\Contracts;

use App\Models\SubscriptionPayment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SubscriptionPaymentRepositoryInterface
{
    public function create(array $data): SubscriptionPayment;

    public function findById(string $id): ?SubscriptionPayment;

    public function paginateForAdmin(
        int $perPage = 15,
        ?string $from = null,
        ?string $to = null,
        ?string $planId = null,
        ?string $status = null,
        ?string $salonId = null,
    ): LengthAwarePaginator;
}
