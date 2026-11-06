<?php

namespace App\Models;

use App\Enums\PaymentGateway;
use App\Enums\PaymentTransactionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'salon_id',
        'plan_id',
        'amount',
        'currency',
        'reference',
        'gateway_ref',
        'status',
        'paid_at',
        'idempotency_key',
        'checkout_url',
        'checkout_expires_at',
        'gateway',
        'gateway_amount',
        'gateway_fees',
        'gateway_net_amount',
        'review_reason',
        'last_checked_at',
        'refund_check_pending_at',
        'refund_check_attempts',
    ];

    /** Les nouvelles transactions passent par GeniusPay (la colonne a le même défaut en base). */
    protected $attributes = [
        'gateway' => 'geniuspay',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => PaymentTransactionStatus::class,
            'paid_at' => 'datetime',
            'checkout_expires_at' => 'datetime',
            'gateway' => PaymentGateway::class,
            'gateway_amount' => 'integer',
            'gateway_fees' => 'integer',
            'gateway_net_amount' => 'integer',
            'last_checked_at' => 'datetime',
            'refund_check_pending_at' => 'datetime',
            'refund_check_attempts' => 'integer',
        ];
    }

    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
