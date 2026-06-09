<?php

namespace App\Repositories\Eloquent;

use App\Models\Salon;
use App\Repositories\Contracts\SalonRepositoryInterface;
use App\Support\IvoryCoastPhone;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SalonRepository implements SalonRepositoryInterface
{
    public function __construct(
        protected Salon $salonModel,
    ) {}

    public function create(array $data): Salon
    {
        return $this->salonModel->create($data);
    }

    public function findById(string $id): ?Salon
    {
        return $this->salonModel->newQuery()->find($id);
    }

    public function findBySlug(string $slug): ?Salon
    {
        return $this->salonModel->newQuery()->where('slug', $slug)->first();
    }

    public function findByWhatsappNumber(string $whatsappNumber): ?Salon
    {
        return $this->salonModel->newQuery()
            ->where('whatsapp_number', IvoryCoastPhone::normalize($whatsappNumber))
            ->where('is_active', true)
            ->first();
    }

    public function update(Salon $salon, array $data): Salon
    {
        $salon->update($data);

        return $salon->refresh();
    }

    public function existsBySlug(string $slug): bool
    {
        return $this->salonModel->newQuery()->where('slug', $slug)->exists();
    }

    public function existsByWhatsappNumber(string $whatsappNumber, ?string $exceptSalonId = null): bool
    {
        $query = $this->salonModel->newQuery()
            ->where('whatsapp_number', IvoryCoastPhone::normalize($whatsappNumber));

        if ($exceptSalonId !== null) {
            $query->where('id', '!=', $exceptSalonId);
        }

        return $query->exists();
    }

    public function paginateForAdmin(
        int $perPage = 15,
        ?string $search = null,
        ?string $city = null,
        ?string $planId = null,
        ?bool $isActive = null,
        ?bool $isSuspended = null,
    ): LengthAwarePaginator {
        $query = $this->salonModel->newQuery()
            ->with(['subscriptions' => fn ($q) => $q->with('plan')->latest('created_at')->limit(1)]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('whatsapp_number', 'like', "%{$search}%");
            });
        }

        if ($city) {
            $query->where('city', $city);
        }

        if ($planId) {
            $query->whereHas('subscriptions', fn ($q) => $q->where('plan_id', $planId));
        }

        if ($isActive !== null) {
            $query->where('is_active', $isActive);
        }

        if ($isSuspended !== null) {
            if ($isSuspended) {
                $query->whereNotNull('suspended_at');
            } else {
                $query->whereNull('suspended_at');
            }
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    public function findByIdWithLatestSubscription(string $id): ?Salon
    {
        return $this->salonModel->newQuery()
            ->with(['subscriptions' => fn ($q) => $q->with('plan')->latest('created_at')->limit(1)])
            ->find($id);
    }

    public function delete(Salon $salon): bool
    {
        return (bool) $salon->delete();
    }
}
