<?php

namespace App\Actions\Service;

use App\Repositories\Contracts\ServiceRepositoryInterface;
use App\Services\Salon\SalonContextService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListSalonServicesAction
{
    public function __construct(
        private SalonContextService $salonContext,
        private ServiceRepositoryInterface $serviceRepository,
    ) {}

    public function execute(int $perPage = 15, ?string $search = null, ?bool $isActive = null): LengthAwarePaginator
    {
        $salon = $this->salonContext->resolveAuthenticatedSalon();

        return $this->serviceRepository->getPaginatedBySalonId($salon->id, $perPage, $search, $isActive);
    }
}
