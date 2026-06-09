<?php

namespace App\Repositories\Eloquent;

use App\Models\Service;
use App\Repositories\Contracts\ServiceRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ServiceRepository implements ServiceRepositoryInterface
{
    public function __construct(
        protected Service $serviceModel,
    ) {}

    public function create(array $data): Service
    {
        return $this->serviceModel->create($data);
    }

    public function findByIdForSalon(string $id, string $salonId): ?Service
    {
        return $this->serviceModel->newQuery()
            ->where('id', $id)
            ->where('salon_id', $salonId)
            ->first();
    }

    public function findActiveByIdForSalon(string $id, string $salonId): ?Service
    {
        return $this->serviceModel->newQuery()
            ->where('id', $id)
            ->where('salon_id', $salonId)
            ->where('is_active', true)
            ->first();
    }

    public function getActiveBySalonId(string $salonId): \Illuminate\Database\Eloquent\Collection
    {
        return $this->serviceModel->newQuery()
            ->where('salon_id', $salonId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function getPaginatedBySalonId(
        string $salonId,
        int $perPage = 15,
        ?string $search = null,
        ?bool $isActive = null,
    ): LengthAwarePaginator {
        $query = $this->serviceModel->newQuery()->where('salon_id', $salonId);

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($isActive !== null) {
            $query->where('is_active', $isActive);
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    public function countActiveBySalonId(string $salonId): int
    {
        return $this->serviceModel->newQuery()
            ->where('salon_id', $salonId)
            ->where('is_active', true)
            ->count();
    }

    public function update(Service $service, array $data): bool
    {
        return $service->update($data);
    }
}
