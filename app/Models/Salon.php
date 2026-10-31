<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Salon extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public const UPDATED_AT = null;

    protected $fillable = [
        'name',
        'slug',
        'phone',
        'whatsapp_number',
        'city',
        'address',
        'logo_url',
        'is_active',
        'suspended_at',
        'checkin_key',
        'late_tolerance_minutes',
    ];

    protected $hidden = [
        'checkin_key',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'suspended_at' => 'datetime',
            'late_tolerance_minutes' => 'integer',
        ];
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /** Salon utilisable par son personnel et pour la réservation publique (actif, non suspendu, non supprimé). */
    public function isOperational(): bool
    {
        return $this->is_active && ! $this->isSuspended() && ! $this->trashed();
    }

    public function subscriptionPayments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(SalonSchedule::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function queueEntries(): HasMany
    {
        return $this->hasMany(QueueEntry::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
