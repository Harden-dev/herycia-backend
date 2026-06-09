<?php

namespace App\Actions\Admin\Salon;

use App\Repositories\Contracts\SalonRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListAdminSalonsAction
{
    public function __construct(
        private SalonRepositoryInterface $salonRepository,
    ) {}

    public function execute(
        int $perPage = 15,
        ?string $search = null,
        ?string $city = null,
        ?string $planId = null,
        ?bool $isActive = null,
        ?bool $isSuspended = null,
    ): LengthAwarePaginator {
        return $this->salonRepository->paginateForAdmin(
            $perPage,
            $search,
            $city,
            $planId,
            $isActive,
            $isSuspended,
        );
    }
}
