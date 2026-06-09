<?php

namespace App\Models;

use App\Enums\BillingPaymentMethod;
use App\Enums\BillingPaymentStatus;
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
        'method',
        'status',
        'reference',
        'period_start',
        'period_end',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'method' => BillingPaymentMethod::class,
            'status' => BillingPaymentStatus::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'paid_at' => 'datetime',
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
