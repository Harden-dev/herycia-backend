<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Plafonds d'envoi de SMS :
 * - par salon (audit H5) : limite le coût en cas de réservations massives automatisées ;
 * - par destinataire, tous salons confondus (pentest P2) : un numéro ne peut pas être bombardé
 *   en répartissant les envois sur plusieurs salons ;
 * - global pour les SMS de bienvenue (pentest P1) : l'inscription est ouverte et le numéro non vérifié.
 */
class SmsQuota
{
    private const DAY_SECONDS = 86400;

    private const HOUR_SECONDS = 3600;

    public static function consume(string $salonId): bool
    {
        $key = 'sms-quota:'.$salonId;
        $limit = (int) config('sms.daily_limit_per_salon', 200);

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            Log::warning('SMS quota reached for salon', ['salon_id' => $salonId, 'limit' => $limit]);

            return false;
        }

        RateLimiter::hit($key, self::DAY_SECONDS);

        return true;
    }

    public static function consumeForRecipient(string $toE164): bool
    {
        $key = self::recipientKey($toE164);
        $limit = (int) config('sms.daily_limit_per_recipient', 10);

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            Log::warning('SMS quota reached for recipient', ['to' => Pii::maskPhone($toE164), 'limit' => $limit]);

            return false;
        }

        RateLimiter::hit($key, self::DAY_SECONDS);

        return true;
    }

    public static function consumeWelcome(): bool
    {
        $key = 'sms-quota:welcome';
        $limit = (int) config('sms.welcome_hourly_limit', 20);

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            Log::warning('Welcome SMS hourly quota reached', ['limit' => $limit]);

            return false;
        }

        RateLimiter::hit($key, self::HOUR_SECONDS);

        return true;
    }

    /** Clé sans le numéro en clair (cache partagé). */
    private static function recipientKey(string $toE164): string
    {
        return 'sms-quota-recipient:'.hash('sha256', IvoryCoastPhone::normalize($toE164));
    }
}
