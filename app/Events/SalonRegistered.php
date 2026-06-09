<?php

namespace App\Events;

use App\Models\Salon;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SalonRegistered
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Salon $salon,
        public User $user,
        public Subscription $subscription,
    ) {}
}
