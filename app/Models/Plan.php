<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'name',
        'code',
        'price_fcfa',
        'max_employees',
        'max_services',
        'has_online_booking',
        'has_analytics',
        'has_multi_branch',
        'is_archived',
    ];

    protected function casts(): array
    {
        return [
            'price_fcfa' => 'integer',
            'max_employees' => 'integer',
            'max_services' => 'integer',
            'has_online_booking' => 'boolean',
            'has_analytics' => 'boolean',
            'has_multi_branch' => 'boolean',
            'is_archived' => 'boolean',
        ];
    }

    public function subscriptionPayments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function hasUnlimitedEmployees(): bool
    {
        return $this->max_employees === null;
    }

    public function allowsMoreEmployees(int $currentActiveCount): bool
    {
        if ($this->hasUnlimitedEmployees()) {
            return true;
        }

        return $currentActiveCount < $this->max_employees;
    }
}

