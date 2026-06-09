<?php

namespace App\Actions\Admin\Plan;

use App\Models\Plan;
use App\Repositories\Contracts\PlanRepositoryInterface;
use RuntimeException;

class ArchiveAdminPlanAction
{
    public function __construct(
        private PlanRepositoryInterface $planRepository,
    ) {}

    public function execute(string $id): Plan
    {
        $plan = $this->planRepository->findById($id);

        if (! $plan) {
            throw new RuntimeException('Plan introuvable.');
        }

        $this->planRepository->update($plan, ['is_archived' => true]);

        return $plan->refresh();
    }
}
