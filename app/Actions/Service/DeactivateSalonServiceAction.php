<?php

namespace App\Actions\Service;

use App\Models\Service;
use App\Repositories\Contracts\ServiceRepositoryInterface;
use App\Services\Salon\SalonContextService;

class DeactivateSalonServiceAction
{
    public function __construct(
        private SalonContextService $salonContext,
        private ServiceRepositoryInterface $serviceRepository,
    ) {}

    public function execute(string $serviceId): Service
    {
        $service = $this->salonContext->resolveSalonService($serviceId);

        if (! $service->is_active) {
            return $service;
        }

        $this->serviceRepository->update($service, ['is_active' => false]);

        return $service->refresh();
    }
}
