<?php

namespace App\Repositories\Eloquent;

use App\Enums\AdminUserType;
use App\Enums\SalonStaffRole;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Support\IvoryCoastPhone;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class UserRepository implements UserRepositoryInterface
{
    public function __construct(
        protected User $userModel,
    ) {}

    public function create(array $data): User
    {
        return $this->userModel->create($data);
    }

    public function findById(string $id): ?User
    {
        // Pas de cache : le modèle complet (hash du mot de passe, statut) ne doit pas être
        // servi périmé ni stocké sérialisé dans Redis (audit H10).
        return $this->userModel->find($id);
    }

    public function findByIdForSalon(string $id, string $salonId): ?User
    {
        return $this->userModel->newQuery()
            ->where('id', $id)
            ->where('salon_id', $salonId)
            ->first();
    }

    public function getActiveStylistsBySalonId(string $salonId): Collection
    {
        return $this->userModel->newQuery()
            ->where('salon_id', $salonId)
            ->where('role', SalonStaffRole::Stylist)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function findByEmail(string $email): ?User
    {
        return $this->userModel->where('email', $email)->first();
    }

    public function findByPhone(string $phone): ?User
    {
        return $this->userModel->where('phone', IvoryCoastPhone::normalize($phone))->first();
    }

    public function existsByEmail(string $email, ?string $exceptUserId = null): bool
    {
        $query = $this->userModel->newQuery()->where('email', $email);

        if ($exceptUserId !== null) {
            $query->where('id', '!=', $exceptUserId);
        }

        return $query->exists();
    }

    public function existsByPhone(string $phone, ?string $exceptUserId = null): bool
    {
        $query = $this->userModel->newQuery()->where('phone', IvoryCoastPhone::normalize($phone));

        if ($exceptUserId !== null) {
            $query->where('id', '!=', $exceptUserId);
        }

        return $query->exists();
    }

    public function findUnverifiedByEmail(string $email): ?User
    {
        return $this->userModel
            ->where('email', $email)
            ->where('is_active', false)
            ->first();
    }

    public function findUnverifiedByPhone(string $phone): ?User
    {
        return $this->userModel
            ->where('phone', IvoryCoastPhone::normalize($phone))
            ->where('is_active', false)
            ->first();
    }

    public function delete(User $user): bool
    {
        return $user->delete();
    }

    public function update(User $user, array $data): bool
    {
        $updated = $user->update($data);
        if ($updated) {
            Cache::forget("user.{$user->id}");
        }

        return $updated;
    }

    public function toggleStatus(User $user, bool $isActive): bool
    {
        $toggled = $user->update(['is_active' => $isActive]);
        if ($toggled) {
            Cache::forget("user.{$user->id}");
        }

        return $toggled;
    }

    public function getAll(): Collection
    {
        return $this->userModel->all();
    }

    public function getPaginatedBySalon(
        string $salonId,
        int $perPage = 15,
        ?string $search = null,
        ?bool $isActive = null,
    ): LengthAwarePaginator {
        $query = $this->userModel->newQuery()->where('salon_id', $salonId);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($isActive !== null) {
            $query->where('is_active', $isActive);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function countActiveBySalon(string $salonId): int
    {
        return $this->userModel->newQuery()
            ->where('salon_id', $salonId)
            ->where('is_active', true)
            ->count();
    }

    public function getPaginated(int $perPage = 15, ?string $search = null, ?bool $isActive = null): LengthAwarePaginator
    {
        return $this->paginateForAdmin($perPage, $search, $isActive);
    }

    public function paginateForAdmin(
        int $perPage = 15,
        ?string $search = null,
        ?bool $isActive = null,
        ?string $userType = null,
    ): LengthAwarePaginator {
        $query = $this->userModel->newQuery()->with(['salon:id,name,city']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($isActive !== null) {
            $query->where('is_active', $isActive);
        }

        if ($userType !== null) {
            $type = AdminUserType::from($userType);

            match ($type) {
                AdminUserType::SuperAdmin => $query->where('role', SalonStaffRole::SuperAdmin),
                AdminUserType::Owner => $query->where('role', SalonStaffRole::Admin),
                AdminUserType::Employee => $query->whereIn('role', [
                    SalonStaffRole::Manager,
                    SalonStaffRole::Stylist,
                    SalonStaffRole::Receptionist,
                ]),
            };
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    public function getForSelect(?string $search = null, ?bool $isActive = null, bool $excludeAgents = true): Collection
    {
        $query = $this->userModel->newQuery();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($isActive !== null) {
            $query->where('is_active', $isActive);
        }

        return $query->orderBy('name')->get();
    }
}
