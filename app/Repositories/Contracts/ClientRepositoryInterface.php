<?php

namespace App\Repositories\Contracts;

use App\Models\Client;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ClientRepositoryInterface
{
    public function create(array $data): Client;

    public function findByIdForSalon(string $id, string $salonId): ?Client;

    public function findByIdForSalonWithHistory(string $id, string $salonId): ?Client;

    public function findByPhoneForSalon(string $phone, string $salonId): ?Client;

    public function getPaginatedBySalon(string $salonId, int $perPage = 15, ?string $search = null): LengthAwarePaginator;

    public function existsByPhoneForSalon(string $phone, string $salonId, ?string $exceptClientId = null): bool;

    public function update(Client $client, array $data): bool;
}
