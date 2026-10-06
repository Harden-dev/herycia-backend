<?php

namespace App\Models;

use App\Enums\SalonStaffRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasUuids, Notifiable;

    public const UPDATED_AT = null;

    protected $fillable = [
        'salon_id',
        'name',
        'phone',
        'email',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'role' => SalonStaffRole::class,
            'is_active' => 'boolean',
            'password' => 'hashed',
            'password_changed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Tout changement de mot de passe révoque les JWT émis auparavant (cf. JwtMiddleware).
        static::updating(function (User $user): void {
            if ($user->isDirty('password')) {
                $user->password_changed_at = now();
            }
        });
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'email' => $this->email,
            'name' => $this->name,
            'role' => $this->role?->value,
        ];
    }

    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === SalonStaffRole::SuperAdmin;
    }

    public function hasRole(SalonStaffRole|string $role): bool
    {
        $expected = $role instanceof SalonStaffRole ? $role : SalonStaffRole::from($role);

        return $this->role === $expected;
    }

    /** @param  list<SalonStaffRole|string>  $roles */
    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function queueEntries(): HasMany
    {
        return $this->hasMany(QueueEntry::class);
    }
}
