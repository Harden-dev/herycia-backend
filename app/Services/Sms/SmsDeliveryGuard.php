<?php

namespace App\Services\Sms;

use App\Exceptions\SmsDeliveryException;
use App\Support\SmsQuota;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Envoi SMS « au plus une fois » pour les jobs et listeners en file.
 *
 * - une clé métier par SMS (ex. rappel du RDV X à telle heure) : un SMS déjà parti n'est jamais renvoyé,
 *   même si le job est rejoué ou dispatché deux fois ;
 * - verrou par clé : deux workers ne peuvent pas envoyer le même SMS en parallèle ;
 * - marqueur « in_flight » posé AVANT l'appel Twilio (pentest P3) : si le worker est tué pendant
 *   l'appel, ou si le cache tombe juste après, le réessai ne renvoie pas (mieux vaut manquer qu'envoyer
 *   deux fois). Il n'est effacé que sur un échec sûr, quand Twilio n'a certainement rien envoyé ;
 * - quotas (salon, destinataire) décomptés une seule fois par SMS, quel que soit le nombre d'essais ;
 * - un échec « incertain » (le SMS a pu partir) est mémorisé comme traité : pas de nouvel essai.
 */
class SmsDeliveryGuard
{
    public const STATE_IN_FLIGHT = 'in_flight';

    public const STATE_SENT = 'sent';

    public const STATE_FAILED = 'failed';

    private const TTL_SECONDS = 30 * 86400;

    private const LOCK_SECONDS = 60;

    public function __construct(private TwilioSmsService $smsService) {}

    /**
     * @throws SmsDeliveryException retryable = true : le job peut réessayer sans risque de doublon
     */
    public function sendOnce(string $key, ?string $salonId, string $toE164, string $body): void
    {
        if ($this->alreadyHandled($key)) {
            return;
        }

        $lock = Cache::lock('sms:lock:'.$key, self::LOCK_SECONDS);

        if (! $lock->get()) {
            throw new SmsDeliveryException('Envoi SMS déjà en cours pour cette clé.', retryable: true);
        }

        try {
            if ($this->alreadyHandled($key) || ! $this->consumeQuotas($key, $salonId, $toE164)) {
                return;
            }

            $this->deliver($key, $toE164, $body);
        } finally {
            $lock->release();
        }
    }

    private static function handledKey(string $key): string
    {
        return 'sms:handled:'.$key;
    }

    private function deliver(string $key, string $toE164, string $body): void
    {
        Cache::put(self::handledKey($key), self::STATE_IN_FLIGHT, self::TTL_SECONDS);

        try {
            $this->smsService->send($toE164, $body);
        } catch (SmsDeliveryException $e) {
            if ($e->retryable) {
                // Twilio n'a rien envoyé : le réessai du job peut envoyer.
                Cache::forget(self::handledKey($key));
            } else {
                // Définitif ou incertain : on ne renverra pas ce SMS.
                Cache::put(self::handledKey($key), self::STATE_FAILED, self::TTL_SECONDS);
            }

            throw $e;
        }

        Cache::put(self::handledKey($key), self::STATE_SENT, self::TTL_SECONDS);
    }

    private function consumeQuotas(string $key, ?string $salonId, string $toE164): bool
    {
        if ($salonId !== null && ! $this->consumeOnce('sms:quota-consumed:'.$key, fn (): bool => SmsQuota::consume($salonId))) {
            return false;
        }

        return $this->consumeOnce('sms:recipient-consumed:'.$key, fn (): bool => SmsQuota::consumeForRecipient($toE164));
    }

    /** @param  \Closure(): bool  $consume */
    private function consumeOnce(string $markerKey, \Closure $consume): bool
    {
        if (Cache::has($markerKey)) {
            return true;
        }

        if (! $consume()) {
            return false;
        }

        Cache::put($markerKey, true, self::TTL_SECONDS);

        return true;
    }

    private function alreadyHandled(string $key): bool
    {
        $state = Cache::get(self::handledKey($key));

        if ($state === null) {
            return false;
        }

        if ($state === self::STATE_IN_FLIGHT) {
            Log::warning('SMS non renvoyé : envoi précédent en cours ou interrompu, état incertain.', ['key' => $key]);
        } else {
            Log::info('SMS ignoré : déjà traité.', ['key' => $key, 'state' => $state]);
        }

        return true;
    }
}
