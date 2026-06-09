<?php

namespace App\Repositories\Contracts;

use App\Models\Service;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ServiceRepositoryInterface
{
    public function create(array $data): Service;

    public function findByIdForSalon(string $id, string $salonId): ?Service;

    public function findActiveByIdForSalon(string $id, string $salonId): ?Service;

    /** @return \Illuminate\Database\Eloquent\Collection<int, Service> */
    public function getActiveBySalonId(string $salonId): \Illuminate\Database\Eloquent\Collection;

    public function getPaginatedBySalonId(
        string $salonId,
        int $perPage = 15,
        ?string $search = null,
        ?bool $isActive = null,
    ): LengthAwarePaginator;

    public function countActiveBySalonId(string $salonId): int;

    public function update(Service $service, array $data): bool;
}
