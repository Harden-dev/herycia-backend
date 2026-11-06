<?php

namespace App\Jobs\Sms;

use App\Enums\QueueEntryStatus;
use App\Jobs\Sms\Concerns\SendsSmsOnce;
use App\Models\QueueEntry;
use App\Services\PublicLinkService;
use App\Support\IvoryCoastPhone;
use App\Support\SmsText;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;

/** File d'attente V2 : « C'est bientôt votre tour ». */
class SendQueueSoonSms implements ShouldQueue
{
    use Queueable;
    use SendsSmsOnce;
    use SerializesModels;

    public int $timeout = 30;

    public function __construct(public QueueEntry $entry) {}

    public function handle(PublicLinkService $linkService): void
    {
        $entry = $this->entry->fresh(['client', 'staff', 'salon']);

        if ($entry === null || ! in_array($entry->status, [QueueEntryStatus::Waiting, QueueEntryStatus::Called], true)) {
            return;
        }

        $phone = IvoryCoastPhone::normalize((string) $entry->client?->phone);

        if (! IvoryCoastPhone::isValid($phone)) {
            return;
        }

        $body = 'Bonjour '.SmsText::clean($entry->client?->name)." !\n\n".
            "C'est bientôt votre tour chez *".SmsText::clean($entry->salon?->name).'*'.
            ($entry->staff?->name ? ' avec '.SmsText::clean($entry->staff->name) : '').
            ". Merci de vous rapprocher de l'accueil.";

        if ($entry->tracking_token !== null) {
            $body .= "\n\nVotre position : ".$linkService->buildQueueTrackingLink($entry->tracking_token);
        }

        $this->sendSmsOnce('queue-soon:'.$entry->id, $entry->salon_id, '+'.$phone, $body);
    }
}
