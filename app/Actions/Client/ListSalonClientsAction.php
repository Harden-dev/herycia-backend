<?php

namespace App\Actions\Client;

use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Services\Salon\SalonContextService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListSalonClientsAction
{
    public function __construct(
        private SalonContextService $salonContext,
        private ClientRepositoryInterface $clientRepository,
    ) {}

    public function execute(int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        $salon = $this->salonContext->resolveAuthenticatedSalon();

        return $this->clientRepository->getPaginatedBySalon($salon->id, $perPage, $search);
    }
}
