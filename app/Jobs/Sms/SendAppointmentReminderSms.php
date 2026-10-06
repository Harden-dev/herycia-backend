<?php

namespace App\Jobs\Sms;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Services\PublicLinkService;
use App\Services\Sms\TwilioSmsService;
use App\Support\IvoryCoastPhone;
use App\Support\SmsQuota;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;

class SendAppointmentReminderSms implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public Appointment $appointment) {}

    public function handle(TwilioSmsService $smsService, PublicLinkService $linkService): void
    {
        $appointment = $this->appointment->fresh(['client', 'service', 'staff', 'salon']);

        if ($appointment === null) {
            return;
        }

        if (in_array($appointment->status, [AppointmentStatus::Cancelled, AppointmentStatus::NoShow], true)) {
            return;
        }

        $client = $appointment->client;

        if ($client === null || $client->phone === '') {
            return;
        }

        if (! SmsQuota::consume($appointment->salon_id)) {
            return;
        }

        $phone = IvoryCoastPhone::normalize($client->phone);

        if (! IvoryCoastPhone::isValid($phone)) {
            return;
        }

        $scheduledAt = Carbon::parse($appointment->scheduled_at);
        $trackingLink = $appointment->tracking_token !== null
            ? $linkService->buildTrackingLink($appointment->tracking_token)
            : null;

        $body = "⏰ Rappel de rendez-vous !\n\n".
            "Vous avez un rendez-vous dans *1 heure* :\n\n".
            "💇 {$appointment->service->name}\n".
            '📅 '.$scheduledAt->format('d/m/Y à H\hi')."\n".
            "✂️ Avec {$appointment->staff->name}\n".
            "📍 {$appointment->salon->name} — {$appointment->salon->city}\n\n".
            'À tout à l\'heure ! 😊';

        if ($trackingLink !== null) {
            $body .= "\n\nSuivez votre RDV : {$trackingLink}";
        }

        $smsService->send('+'.$phone, $body);
    }
}
