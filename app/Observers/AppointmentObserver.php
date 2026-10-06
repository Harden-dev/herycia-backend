<?php

namespace App\Observers;

use App\Jobs\Sms\SendAppointmentReminderSms;
use App\Jobs\Sms\SendAppointmentTrackingSms;
use App\Models\Appointment;
use Illuminate\Support\Str;

class AppointmentObserver
{
    public function creating(Appointment $appointment): void
    {
        if ($appointment->tracking_token !== null) {
            return;
        }

        do {
            $token = Str::random(10);
        } while (Appointment::query()->where('tracking_token', $token)->exists());

        $appointment->tracking_token = $token;
    }

    public function created(Appointment $appointment): void
    {
        // afterCommit : pas de SMS pour un rendez-vous annulé par un rollback (audit M5)
        SendAppointmentTrackingSms::dispatch($appointment)->afterCommit();

        $reminderAt = $appointment->scheduled_at->copy()->subHour();

        if ($reminderAt->isFuture()) {
            SendAppointmentReminderSms::dispatch($appointment)->delay($reminderAt)->afterCommit();
        }
    }
}
