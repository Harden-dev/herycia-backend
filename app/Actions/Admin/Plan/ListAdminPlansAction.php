<?php

namespace App\Actions\Admin\Plan;

use App\Repositories\Contracts\PlanRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListAdminPlansAction
{
    public function __construct(
        private PlanRepositoryInterface $planRepository,
    ) {}

    public function execute(int $perPage = 15, ?bool $includeArchived = null): LengthAwarePaginator
    {
        return $this->planRepository->paginateForAdmin($perPage, $includeArchived);
    }
}
