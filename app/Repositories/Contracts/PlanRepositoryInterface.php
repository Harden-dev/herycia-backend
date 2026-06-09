<?php

namespace App\Repositories\Contracts;

use App\Models\Plan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface PlanRepositoryInterface
{
    public function findByCode(string $code): ?Plan;

    public function findRegisterableByCode(string $code): ?Plan;

    public function findById(string $id): ?Plan;

    public function create(array $data): Plan;

    public function update(Plan $plan, array $data): Plan;

    public function getAll(bool $includeArchived = false): Collection;

    public function paginateForAdmin(int $perPage = 15, ?bool $includeArchived = null): LengthAwarePaginator;

    public function existsByCode(string $code, ?string $exceptPlanId = null): bool;
}
