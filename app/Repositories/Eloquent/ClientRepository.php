<?php

namespace App\Repositories\Eloquent;

use App\Models\Client;
use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Support\IvoryCoastPhone;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ClientRepository implements ClientRepositoryInterface
{
    public function __construct(
        protected Client $clientModel,
    ) {}

    public function create(array $data): Client
    {
        return $this->clientModel->create($data);
    }

    public function findByIdForSalon(string $id, string $salonId): ?Client
    {
        return $this->clientModel->newQuery()
            ->where('id', $id)
            ->where('salon_id', $salonId)
            ->first();
    }

    public function findByIdForSalonWithHistory(string $id, string $salonId): ?Client
    {
        return $this->clientModel->newQuery()
            ->where('id', $id)
            ->where('salon_id', $salonId)
            ->with([
                'appointments' => fn ($query) => $query
                    ->orderByDesc('scheduled_at')
                    ->with(['service', 'staff']),
                'payments' => fn ($query) => $query->orderByDesc('paid_at'),
            ])
            ->first();
    }

    public function findByPhoneForSalon(string $phone, string $salonId): ?Client
    {
        return $this->clientModel->newQuery()
            ->where('salon_id', $salonId)
            ->where('phone', IvoryCoastPhone::normalize($phone))
            ->first();
    }

    public function getPaginatedBySalon(string $salonId, int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        $query = $this->clientModel->newQuery()->where('salon_id', $salonId);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('whatsapp_id', 'like', "%{$search}%");
            });
        }

        return $query
            ->orderByDesc('last_visit_at')
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function existsByPhoneForSalon(string $phone, string $salonId, ?string $exceptClientId = null): bool
    {
        $query = $this->clientModel->newQuery()
            ->where('salon_id', $salonId)
            ->where('phone', IvoryCoastPhone::normalize($phone));

        if ($exceptClientId !== null) {
            $query->where('id', '!=', $exceptClientId);
        }

        return $query->exists();
    }

    public function update(Client $client, array $data): bool
    {
        return $client->update($data);
    }
}
