<?php

namespace App\Listeners;

use App\Events\SalonRegistered;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/** Traité en file après le commit de la transaction qui émet l'événement (jamais sur rollback). */
class LogSalonRegistered implements ShouldHandleEventsAfterCommit, ShouldQueue
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
