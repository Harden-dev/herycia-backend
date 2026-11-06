<?php

namespace App\Listeners;

use App\Events\UserCreated;
use App\Services\User\UserNotification;
use App\Support\Pii;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/** Traité en file après le commit de la transaction qui émet l'événement (jamais sur rollback). */
class SendUserCreatedNotifications implements ShouldHandleEventsAfterCommit, ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(UserCreated $event): void
    {
        try {
            $user = $event->user;

            // Guard contre la double exécution avec Redis
            $lockKey = "user_created_notification_lock:{$user->id}";

            // Si le flag existe déjà, on skip (déjà traité)
            if (Redis::exists($lockKey)) {
                Log::info('SendUserCreatedNotifications SKIPPED (already processed)', [
                    'user_id' => $user->id,
                ]);

                return;
            }

            // Poser le flag (expire dans 1 heure)
            Redis::setex($lockKey, 3600, '1');

            Log::info('SendUserCreatedNotifications LISTENER CALLED', [
                'user_id' => $user->id,
            ]);

            // Désactivé : Vérification d'email par lien (non utilisé pour app mobile)
            // La vérification se fait uniquement via OTP lors de l'inscription
            // L'OTP sert déjà de vérification email/phone
            Log::info('Email verification by link disabled - using OTP only', [
                'user_id' => $user->id,
                'email' => Pii::maskEmail($user->email),
                'email_verified' => $user->hasVerifiedEmail(),
            ]);

            // Envoyer l'email de bienvenue (une seule fois)
            $notification = new UserNotification($user);
            $notification->sendWelcomeEmail();

        } catch (\Exception $e) {
            Log::error('Error sending user created notifications: '.$e->getMessage(), [
                'user_id' => $event->user->id,
            ]);
        }
    }
}
