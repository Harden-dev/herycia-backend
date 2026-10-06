<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Plafond d'envoi de SMS par salon (audit H5) : limite le coût en cas de réservations
 * massives automatisées vers des numéros arbitraires.
 */
class SmsQuota
{
    public static function consume(string $salonId): bool
    {
        $key = 'sms-quota:'.$salonId;
        $limit = (int) config('sms.daily_limit_per_salon', 200);

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            Log::warning('SMS quota reached for salon', ['salon_id' => $salonId, 'limit' => $limit]);

            return false;
        }

        RateLimiter::hit($key, 86400);

        return true;
    }

    /** Nom saisi publiquement : retire les caractères de contrôle et les liens, tronque. */
    public static function sanitizeName(?string $name): string
    {
        $clean = preg_replace('/[\p{C}]+/u', ' ', (string) $name) ?? '';
        $clean = preg_replace('#(https?://|www\.)\S+#i', '', $clean) ?? '';

        return Str::limit(trim($clean), 40, '');
    }
}
