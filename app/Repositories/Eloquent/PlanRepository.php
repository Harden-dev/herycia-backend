<?php

namespace App\Repositories\Eloquent;

use App\Models\Plan;
use App\Repositories\Contracts\PlanRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class PlanRepository implements PlanRepositoryInterface
{
    public function __construct(
        protected Plan $planModel,
    ) {}

    public function findByCode(string $code): ?Plan
    {
        return $this->planModel->newQuery()->where('code', $code)->first();
    }

    public function findRegisterableByCode(string $code): ?Plan
    {
        return $this->planModel->newQuery()
            ->where('code', $code)
            ->where('is_archived', false)
            ->first();
    }

    public function findById(string $id): ?Plan
    {
        return $this->planModel->newQuery()->find($id);
    }

    public function create(array $data): Plan
    {
        return $this->planModel->create($data);
    }

    public function update(Plan $plan, array $data): Plan
    {
        $plan->update($data);

        return $plan->refresh();
    }

    public function getAll(bool $includeArchived = false): Collection
    {
        $query = $this->planModel->newQuery()->orderBy('price_fcfa');

        if (! $includeArchived) {
            $query->where('is_archived', false);
        }

        return $query->get();
    }

    public function paginateForAdmin(int $perPage = 15, ?bool $includeArchived = null): LengthAwarePaginator
    {
        $query = $this->planModel->newQuery()->orderBy('price_fcfa');

        if ($includeArchived === false) {
            $query->where('is_archived', false);
        } elseif ($includeArchived === true) {
            $query->where('is_archived', true);
        }

        return $query->paginate($perPage);
    }

    public function existsByCode(string $code, ?string $exceptPlanId = null): bool
    {
        $query = $this->planModel->newQuery()->where('code', $code);

        if ($exceptPlanId !== null) {
            $query->where('id', '!=', $exceptPlanId);
        }

        return $query->exists();
    }
}
