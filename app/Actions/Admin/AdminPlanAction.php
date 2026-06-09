<?php

namespace App\Actions\Admin;

use App\Data\Admin\CreateAdminPlanData;
use App\Data\Admin\UpdateAdminPlanData;
use App\Models\Plan;
use App\Repositories\Contracts\PlanRepositoryInterface;

class AdminPlanAction
{
    public function __construct(
        private PlanRepositoryInterface $planRepository,
    ) {}

    public function index(
        int $perPage = 15,
        ?bool $includeArchived = null,
    ): \Illuminate\Contracts\Pagination\LengthAwarePaginator {
        return $this->planRepository->paginateForAdmin($perPage, $includeArchived);
    }

    public function store(CreateAdminPlanData $data): Plan
    {
        if ($this->planRepository->existsByCode($data->code)) {
            throw new \RuntimeException('Un plan avec ce code existe déjà.');
        }

        return $this->planRepository->create([
            'name' => $data->name,
            'code' => $data->code,
            'price_fcfa' => $data->priceFcfa,
            'max_employees' => $data->maxEmployees,
            'max_services' => $data->maxServices,
            'has_online_booking' => $data->hasOnlineBooking,
            'has_analytics' => $data->hasAnalytics,
            'has_multi_branch' => $data->hasMultiBranch,
        ]);
    }

    public function show(string $planId): Plan
    {
        $plan = $this->planRepository->findById($planId);

        if (! $plan) {
            throw new \RuntimeException('Plan introuvable.');
        }

        return $plan;
    }

    public function update(string $planId, UpdateAdminPlanData $data): Plan
    {
        $plan = $this->show($planId);
        $payload = $data->toArray();

        if ($payload === []) {
            throw new \RuntimeException('Aucune donnée à mettre à jour.');
        }

        if (isset($payload['code']) && $this->planRepository->existsByCode($payload['code'], $plan->id)) {
            throw new \RuntimeException('Un plan avec ce code existe déjà.');
        }

        return $this->planRepository->update($plan, $payload);
    }

    public function archive(string $planId): Plan
    {
        $plan = $this->show($planId);

        return $this->planRepository->update($plan, [
            'is_archived' => true,
        ]);
    }
}
