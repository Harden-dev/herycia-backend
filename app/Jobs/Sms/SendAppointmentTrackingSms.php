<?php

namespace App\Jobs\Sms;

use App\Models\Appointment;
use App\Services\PublicLinkService;
use App\Services\Sms\TwilioSmsService;
use App\Support\IvoryCoastPhone;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;

class SendAppointmentTrackingSms implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public Appointment $appointment) {}

    public function handle(TwilioSmsService $smsService, PublicLinkService $linkService): void
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

        $body = "Bonjour {$client->name} !\n\n".
            "Votre rendez-vous chez *{$appointment->salon->name}* est confirmé.\n\n".
            "💇 {$appointment->service->name}\n".
            '📅 '.$scheduledAt->format('d/m/Y à H\hi')."\n".
            "✂️ Avec {$appointment->staff->name}\n\n".
            "Suivez votre RDV ici :\n{$trackingLink}";

        $smsService->send('+'.$phone, $body);
    }
}
