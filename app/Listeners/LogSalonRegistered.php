<?php

namespace App\Listeners;

use App\Events\SalonRegistered;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class LogSalonRegistered implements ShouldQueue
{
    public function handle(SalonRegistered $event): void
    {
        Log::info('Salon registered', [
            'salon_id' => $event->salon->id,
            'salon_slug' => $event->salon->slug,
            'user_id' => $event->user->id,
            'subscription_id' => $event->subscription->id,
        ]);
    }
}
