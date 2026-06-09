<?php

namespace App\Repositories\Contracts;

use App\Models\Salon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SalonRepositoryInterface
{
    public function create(array $data): Salon;

    public function findById(string $id): ?Salon;

    public function findBySlug(string $slug): ?Salon;

    public function findByWhatsappNumber(string $whatsappNumber): ?Salon;

    public function update(Salon $salon, array $data): Salon;

    public function existsBySlug(string $slug): bool;

    public function existsByWhatsappNumber(string $whatsappNumber, ?string $exceptSalonId = null): bool;

    public function paginateForAdmin(
        int $perPage = 15,
        ?string $search = null,
        ?string $city = null,
        ?string $planId = null,
        ?bool $isActive = null,
        ?bool $isSuspended = null,
    ): LengthAwarePaginator;

    public function findByIdWithLatestSubscription(string $id): ?Salon;

    public function delete(Salon $salon): bool;
}
