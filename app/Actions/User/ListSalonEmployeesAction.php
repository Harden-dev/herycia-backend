<?php

namespace App\Actions\User;

use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Salon\SalonContextService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListSalonEmployeesAction
{
    public function __construct(
        private SalonContextService $salonContext,
        private UserRepositoryInterface $userRepository,
    ) {}

    public function execute(int $perPage = 15, ?string $search = null, ?bool $isActive = null): LengthAwarePaginator
    {
        $salon = $this->salonContext->resolveAuthenticatedSalon();

        return $this->userRepository->getPaginatedBySalon($salon->id, $perPage, $search, $isActive);
    }
}
