<?php

namespace App\Listeners;

use App\Events\UserCreated;
use App\Services\User\UserNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class SendUserCreatedNotifications implements ShouldQueue
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
                'email' => $user->email,
                'email_verified' => $user->hasVerifiedEmail(),
            ]);

            // Envoyer l'email de bienvenue (une seule fois)
            $notification = new UserNotification($user);
            $notification->sendWelcomeEmail();

        } catch (\Exception $e) {
            Log::error('Error sending user created notifications: ' . $e->getMessage(), [
                'user_id' => $event->user->id,
            ]);
        }
    }
}

