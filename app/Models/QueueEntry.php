<?php

namespace App\Models;

use App\Enums\QueueEntrySource;
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
        'appointment_id',
        'source',
        'priority_at',
        'started_at',
        'tracking_token',
        'soon_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'status' => QueueEntryStatus::class,
            'arrived_at' => 'datetime',
            'called_at' => 'datetime',
            'done_at' => 'datetime',
            'source' => QueueEntrySource::class,
            'priority_at' => 'datetime',
            'started_at' => 'datetime',
            'soon_notified_at' => 'datetime',
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

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /** Entrée encore active (pas terminée ni retirée). */
    public function isActive(): bool
    {
        return in_array($this->status, [
            QueueEntryStatus::Waiting,
            QueueEntryStatus::Called,
            QueueEntryStatus::InService,
        ], true);
    }
}
