<?php

namespace App\Services\Sms;

use App\Exceptions\SmsDeliveryException;
use App\Support\Pii;
use Illuminate\Support\Facades\Log;
use Throwable;
use Twilio\Exceptions\RestException;
use Twilio\Http\CurlClient;
use Twilio\Rest\Client;

class TwilioSmsService
{
    /**
     * Délai total de l'appel Twilio, nettement inférieur au $timeout des jobs SMS (30 s) :
     * le job n'est jamais tué par le worker pendant l'appel (pentest P3).
     */
    public const HTTP_TIMEOUT_SECONDS = 15;

    private const CONNECT_TIMEOUT_SECONDS = 5;

    /**
     * Réponses Twilio sûres à réessayer : le message n'a pas été créé.
     * 502 / 504 sont exclus : la passerelle a pu transmettre la requête avant l'erreur.
     */
    private const RETRYABLE_STATUS_CODES = [429, 500, 503];

    /** Erreurs cURL survenues avant l'envoi de la requête : le SMS n'est certainement pas parti. */
    private const NOT_SENT_NETWORK_ERRORS = [
        'could not resolve host',
        'failed to connect',
        'connection refused',
        "couldn't connect",
    ];

    /**
     * Envoie un SMS si {@see config('sms.notifications_enabled')} est true.
     *
     * @throws SmsDeliveryException
     */
    public function send(string $toE164, string $body): void
    {
        if (! config('sms.notifications_enabled')) {
            Log::debug('SMS notification skipped: SMS_NOTIFICATIONS_ENABLED is false', [
                'to' => Pii::maskPhone($toE164),
            ]);

            return;
        }

        $sid = config('services.twilio.account_sid');
        $token = config('services.twilio.auth_token');
        $from = config('services.twilio.from');

        if (empty($sid) || empty($token) || empty($from)) {
            throw new SmsDeliveryException(
                'SMS_NOTIFICATIONS_ENABLED est activé mais la configuration Twilio est incomplète (TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN, TWILIO_FROM_NUMBER).',
                retryable: false,
            );
        }

        try {
            $this->client($sid, $token)->messages->create($toE164, [
                'from' => $from,
                'body' => $body,
            ]);
            Log::info('SMS sent via Twilio', ['to' => Pii::maskPhone($toE164)]);
        } catch (Throwable $e) {
            $retryable = $this->isSafeToRetry($e);
            $reason = $this->describe($e);

            Log::error('Twilio SMS failed', [
                'to' => Pii::maskPhone($toE164),
                'reason' => $reason,
                'retryable' => $retryable,
            ]);

            // Pas d'exception précédente : son message (qui contient le numéro) finirait dans failed_jobs.
            throw new SmsDeliveryException('Envoi SMS impossible : '.$reason, $retryable);
        }
    }

    private function client(string $sid, string $token): Client
    {
        $http = new CurlClient([
            CURLOPT_TIMEOUT => self::HTTP_TIMEOUT_SECONDS,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT_SECONDS,
        ]);

        return new Client($sid, $token, null, null, $http);
    }

    /** Résumé sans donnée personnelle : statut HTTP et code Twilio, ou message réseau masqué. */
    private function describe(Throwable $e): string
    {
        if ($e instanceof RestException) {
            return sprintf('HTTP %d, code Twilio %d', $e->getStatusCode(), $e->getCode());
        }

        return class_basename($e).': '.Pii::redact($e->getMessage());
    }

    private function isSafeToRetry(Throwable $e): bool
    {
        // Réponse explicite de Twilio : seuls « trop de requêtes » et 500 / 503 sont temporaires
        // et sans envoi ; un 4xx (numéro invalide, compte bloqué…) échouerait à nouveau.
        if ($e instanceof RestException) {
            return in_array($e->getStatusCode(), self::RETRYABLE_STATUS_CODES, true);
        }

        $message = strtolower($e->getMessage());

        foreach (self::NOT_SENT_NETWORK_ERRORS as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        // Délai dépassé ou erreur inconnue : la requête a pu aboutir, on ne renvoie pas.
        return false;
    }
}
