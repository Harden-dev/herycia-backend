<?php

namespace App\Actions\Plan;

use App\Repositories\Contracts\PlanRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class ListPublicPlansAction
{
    public function __construct(
        private PlanRepositoryInterface $planRepository,
    ) {}

    public function execute(): Collection
    {
        return $this->planRepository->getAll(includeArchived: false);
    }
}
