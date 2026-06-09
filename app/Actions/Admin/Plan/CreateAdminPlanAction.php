<?php

namespace App\Actions\Admin\Plan;

use App\Data\Admin\CreateAdminPlanData;
use App\Models\Plan;
use App\Repositories\Contracts\PlanRepositoryInterface;
use RuntimeException;

class CreateAdminPlanAction
{
    public function __construct(
        private PlanRepositoryInterface $planRepository,
    ) {}

    public function execute(CreateAdminPlanData $data): Plan
    {
        if ($this->planRepository->existsByCode($data->code)) {
            throw new RuntimeException('Ce code de plan existe déjà.');
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
}
