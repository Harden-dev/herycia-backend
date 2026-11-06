<?php

namespace App\Models;

use App\Enums\BillingPaymentMethod;
use App\Enums\BillingPaymentStatus;
use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPayment extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'salon_id',
        'subscription_id',
        'plan_id',
        'amount',
        'gateway_amount',
        'method',
        'status',
        'reference',
        'period_start',
        'period_end',
        'paid_at',
        'previous_plan_id',
        'previous_ends_at',
        'previous_status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'gateway_amount' => 'integer',
            'method' => BillingPaymentMethod::class,
            'status' => BillingPaymentStatus::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'paid_at' => 'datetime',
            'previous_ends_at' => 'date',
            'previous_status' => SubscriptionStatus::class,
        ];
    }

    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
