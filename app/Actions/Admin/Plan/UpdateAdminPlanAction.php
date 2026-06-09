<?php

namespace App\Actions\Admin\Plan;

use App\Data\Admin\UpdateAdminPlanData;
use App\Models\Plan;
use App\Repositories\Contracts\PlanRepositoryInterface;
use RuntimeException;

class UpdateAdminPlanAction
{
    public function __construct(
        private PlanRepositoryInterface $planRepository,
    ) {}

    public function execute(string $id, UpdateAdminPlanData $data): Plan
    {
        $plan = $this->planRepository->findById($id);

        if (! $plan) {
            throw new RuntimeException('Plan introuvable.');
        }

        $payload = $data->toArray();

        if ($payload === []) {
            throw new RuntimeException('Aucune donnée à mettre à jour.');
        }

        if (isset($payload['code']) && $this->planRepository->existsByCode($payload['code'], $plan->id)) {
            throw new RuntimeException('Ce code de plan existe déjà.');
        }

        $this->planRepository->update($plan, $payload);

        return $plan->refresh();
    }
}
