<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface UserRepositoryInterface
{
    public function create(array $data): User;

    public function findByEmail(string $email): ?User;

    public function findByPhone(string $phone): ?User;

    public function findById(string $id): ?User;

    public function findByIdForSalon(string $id, string $salonId): ?User;

    /** @return \Illuminate\Database\Eloquent\Collection<int, User> */
    public function getActiveStylistsBySalonId(string $salonId): \Illuminate\Database\Eloquent\Collection;

    public function update(User $user, array $data): bool;

    public function getPaginatedBySalon(
        string $salonId,
        int $perPage = 15,
        ?string $search = null,
        ?bool $isActive = null,
    ): LengthAwarePaginator;

    public function countActiveBySalon(string $salonId): int;

    public function existsByEmail(string $email, ?string $exceptUserId = null): bool;

    public function existsByPhone(string $phone, ?string $exceptUserId = null): bool;

    public function findUnverifiedByEmail(string $email): ?User;

    public function findUnverifiedByPhone(string $phone): ?User;

    public function delete(User $user): bool;

    public function getAll(): Collection;

    public function getPaginated(int $perPage = 15, ?string $search = null, ?bool $isActive = null): LengthAwarePaginator;

    public function paginateForAdmin(
        int $perPage = 15,
        ?string $search = null,
        ?bool $isActive = null,
        ?string $userType = null,
    ): LengthAwarePaginator;

    public function getForSelect(?string $search = null, ?bool $isActive = null, bool $excludeAgents = false): Collection;

    public function toggleStatus(User $user, bool $isActive): bool;
}
