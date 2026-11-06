<?php

namespace App\Jobs\Sms;

use App\Enums\AppointmentStatus;
use App\Jobs\Sms\Concerns\SendsSmsOnce;
use App\Models\Appointment;
use App\Services\PublicLinkService;
use App\Support\IvoryCoastPhone;
use App\Support\SmsText;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;

class SendAppointmentReminderSms implements ShouldQueue
{
    use Queueable;
    use SendsSmsOnce;
    use SerializesModels;

    public int $timeout = 30;

    public function __construct(public Appointment $appointment) {}

    public function handle(PublicLinkService $linkService): void
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
            '💇 '.SmsText::clean($appointment->service?->name)."\n".
            '📅 '.$scheduledAt->format('d/m/Y à H\hi')."\n".
            '✂️ Avec '.SmsText::clean($appointment->staff?->name)."\n".
            '📍 '.SmsText::clean($appointment->salon?->name).' — '.SmsText::clean($appointment->salon?->city)."\n\n".
            'À tout à l\'heure ! 😊';

        if ($trackingLink !== null) {
            $body .= "\n\nSuivez votre RDV : {$trackingLink}";
        }

        // Clé liée à l'horaire : un RDV reprogrammé reçoit bien un nouveau rappel.
        $this->sendSmsOnce(
            'appointment-reminder:'.$appointment->id.':'.$scheduledAt->getTimestamp(),
            $appointment->salon_id,
            '+'.$phone,
            $body,
        );
    }
}
