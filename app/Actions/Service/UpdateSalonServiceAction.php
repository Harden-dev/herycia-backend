<?php

namespace App\Actions\Service;

use App\Data\Service\UpdateSalonServiceData;
use App\Models\Service;
use App\Repositories\Contracts\ServiceRepositoryInterface;
use App\Services\Salon\SalonContextService;

class UpdateSalonServiceAction
{
    public function __construct(
        private SalonContextService $salonContext,
        private ServiceRepositoryInterface $serviceRepository,
    ) {}

    public function execute(string $serviceId, UpdateSalonServiceData $data): Service
    {
        $service = $this->salonContext->resolveSalonService($serviceId);
        $payload = $data->toArray();

        if ($payload === []) {
            throw new \RuntimeException('Aucune donnée à mettre à jour.');
        }

        $this->serviceRepository->update($service, $payload);

        return $service->refresh();
    }
}
