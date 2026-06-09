<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    /** @use HasFactory<\Database\Factories\ClientFactory> */
    use HasFactory, HasUuids;

    public $timestamps = true;

    public const UPDATED_AT = null;

    protected $fillable = [
        'salon_id',
        'name',
        'phone',
        'whatsapp_id',
        'total_visits',
        'last_visit_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'total_visits' => 'integer',
            'last_visit_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class);
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
