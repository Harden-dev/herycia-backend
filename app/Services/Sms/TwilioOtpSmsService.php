<?php

namespace App\Services\Sms;

use App\Support\Pii;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;
use Twilio\Rest\Client;

class TwilioOtpSmsService
{
    /**
     * Envoie le code OTP par SMS si {@see config('sms.otp_enabled')} est true.
     * Sinon : aucun appel API (log debug, pas de fuite du code).
     */
    public function sendRegistrationOtp(string $toE164, string $code): void
    {
        if (! config('sms.otp_enabled')) {
            Log::debug('SMS OTP skipped: SMS_OTP_ENABLED is false', [
                'to' => Pii::maskPhone($toE164),
            ]);

            return;
        }

        $sid = config('services.twilio.account_sid');
        $token = config('services.twilio.auth_token');
        $from = config('services.twilio.from');

        if (empty($sid) || empty($token) || empty($from)) {
            throw new RuntimeException(
                'SMS_OTP_ENABLED est activé mais la configuration Twilio est incomplète (TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN, TWILIO_FROM_NUMBER).'
            );
        }

        $body = sprintf(
            '[%s] Code de vérification : %s. Valide 10 minutes. Ne le partagez pas.',
            config('app.name'),
            $code
        );

        try {
            $client = new Client($sid, $token);
            $client->messages->create($toE164, [
                'from' => $from,
                'body' => $body,
            ]);
            Log::info('SMS OTP sent via Twilio', ['to' => Pii::maskPhone($toE164)]);
        } catch (Throwable $e) {
            $reason = Pii::redact($e->getMessage());
            Log::error('Twilio SMS OTP failed', [
                'to' => Pii::maskPhone($toE164),
                'message' => $reason,
            ]);
            throw new RuntimeException('Envoi du SMS OTP impossible : '.$reason);
        }
    }
}
