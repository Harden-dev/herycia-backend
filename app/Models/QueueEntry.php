<?php

namespace App\Models;

use App\Enums\QueueEntryStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueueEntry extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'salon_id',
        'client_id',
        'user_id',
        'service_id',
        'position',
        'status',
        'arrived_at',
        'called_at',
        'done_at',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'status' => QueueEntryStatus::class,
            'arrived_at' => 'datetime',
            'called_at' => 'datetime',
            'done_at' => 'datetime',
        ];
    }

    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
