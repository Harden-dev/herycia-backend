<?php

namespace App\Jobs\Sms;

use App\Enums\QueueEntryStatus;
use App\Models\QueueEntry;
use App\Services\PublicLinkService;
use App\Services\Sms\TwilioSmsService;
use App\Support\IvoryCoastPhone;
use App\Support\SmsQuota;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;

/** File d'attente V2 : « C'est bientôt votre tour ». */
class SendQueueSoonSms implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public QueueEntry $entry) {}

    public function handle(TwilioSmsService $smsService, PublicLinkService $linkService): void
    {
        $entry = $this->entry->fresh(['client', 'staff', 'salon']);

        if ($entry === null || ! in_array($entry->status, [QueueEntryStatus::Waiting, QueueEntryStatus::Called], true)) {
            return;
        }

        $phone = IvoryCoastPhone::normalize((string) $entry->client?->phone);

        if (! IvoryCoastPhone::isValid($phone) || ! SmsQuota::consume($entry->salon_id)) {
            return;
        }

        $body = 'Bonjour '.SmsQuota::sanitizeName($entry->client?->name)." !\n\n".
            "C'est bientôt votre tour chez *{$entry->salon?->name}*".
            ($entry->staff?->name ? " avec {$entry->staff->name}" : '').
            ". Merci de vous rapprocher de l'accueil.";

        if ($entry->tracking_token !== null) {
            $body .= "\n\nVotre position : ".$linkService->buildQueueTrackingLink($entry->tracking_token);
        }

        $smsService->send('+'.$phone, $body);
    }
}
