<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;
use RuntimeException;
use Twilio\Rest\Client;
use Throwable;

class TwilioSmsService
{
    /**
     * Envoie un SMS si {@see config('sms.notifications_enabled')} est true.
     */
    public function send(string $toE164, string $body): void
    {
        if (! config('sms.notifications_enabled')) {
            Log::debug('SMS notification skipped: SMS_NOTIFICATIONS_ENABLED is false', [
                'to' => $toE164,
            ]);

            return;
        }

        $sid = config('services.twilio.account_sid');
        $token = config('services.twilio.auth_token');
        $from = config('services.twilio.from');

        if (empty($sid) || empty($token) || empty($from)) {
            throw new RuntimeException(
                'SMS_NOTIFICATIONS_ENABLED est activé mais la configuration Twilio est incomplète (TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN, TWILIO_FROM_NUMBER).'
            );
        }

        try {
            $client = new Client($sid, $token);
            $client->messages->create($toE164, [
                'from' => $from,
                'body' => $body,
            ]);
            Log::info('SMS sent via Twilio', ['to' => $toE164]);
        } catch (Throwable $e) {
            Log::error('Twilio SMS failed', [
                'to' => $toE164,
                'message' => $e->getMessage(),
            ]);
            throw new RuntimeException('Envoi SMS impossible : '.$e->getMessage(), 0, $e);
        }
    }
}
