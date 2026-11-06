<?php

namespace App\Jobs\Sms;

use App\Jobs\Sms\Concerns\SendsSmsOnce;
use App\Models\Appointment;
use App\Services\PublicLinkService;
use App\Support\IvoryCoastPhone;
use App\Support\SmsText;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;

class SendAppointmentTrackingSms implements ShouldQueue
{
    use Queueable;
    use SendsSmsOnce;
    use SerializesModels;

    public int $timeout = 30;

    public function __construct(public Appointment $appointment) {}

    public function handle(PublicLinkService $linkService): void
    {
        $appointment = $this->appointment->fresh(['client', 'service', 'staff', 'salon']);

        if ($appointment === null || $appointment->tracking_token === null) {
            return;
        }

        $client = $appointment->client;

        if ($client === null || $client->phone === '') {
            return;
        }

        $phone = IvoryCoastPhone::normalize($client->phone);

        if (! IvoryCoastPhone::isValid($phone)) {
            return;
        }

        $scheduledAt = Carbon::parse($appointment->scheduled_at);
        $trackingLink = $linkService->buildTrackingLink($appointment->tracking_token);

        $body = 'Bonjour '.SmsText::clean($client->name)." !\n\n".
            'Votre rendez-vous chez *'.SmsText::clean($appointment->salon?->name)."* est confirmé.\n\n".
            '💇 '.SmsText::clean($appointment->service?->name)."\n".
            '📅 '.$scheduledAt->format('d/m/Y à H\hi')."\n".
            '✂️ Avec '.SmsText::clean($appointment->staff?->name)."\n\n".
            "Suivez votre RDV ici :\n{$trackingLink}";

        $this->sendSmsOnce(
            'appointment-tracking:'.$appointment->id.':'.$scheduledAt->getTimestamp(),
            $appointment->salon_id,
            '+'.$phone,
            $body,
        );
    }
}
