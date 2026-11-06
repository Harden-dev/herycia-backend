<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Warning agrégé (pentest F5) : au plus un log par clé et par fenêtre ; les occurrences
 * suivantes sont comptées et le total est joint au log de la fenêtre suivante.
 */
final class ThrottledLog
{
    private const PREFIX = 'throttled-log:';

    /** Durée de vie du compteur d'occurrences écartées, au-delà de la fenêtre. */
    private const COUNTER_TTL_SECONDS = 3600;

    /**
     * @param  array<string, mixed>  $context
     */
    public static function warning(string $key, string $message, array $context = [], int $windowSeconds = 60): void
    {
        $windowKey = self::PREFIX.$key;
        $counterKey = $windowKey.':suppressed';

        if (Cache::add($windowKey, true, $windowSeconds)) {
            $suppressed = (int) Cache::pull($counterKey, 0);

            Log::warning($message, $suppressed > 0 ? $context + ['suppressed_since_last_log' => $suppressed] : $context);

            return;
        }

        Cache::add($counterKey, 0, self::COUNTER_TTL_SECONDS);
        Cache::increment($counterKey);
    }
}
