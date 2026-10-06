<?php

namespace App\Services\Queue;

use App\Enums\AppointmentStatus;
use App\Enums\QueueEntrySource;
use App\Enums\QueueEntryStatus;
use App\Jobs\Sms\SendAppointmentReminderSms;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\QueueEntry;
use App\Models\Salon;
use App\Models\User;
use App\Support\IvoryCoastPhone;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Arrivée d'un client avec rendez-vous et choix du retardataire.
 *
 * - À l'heure (jusqu'à la tolérance du salon, 15 min par défaut) : il entre dans la file,
 *   ancré sur l'heure de son rendez-vous.
 * - En retard : rien n'est enregistré, il reçoit deux options et choisit :
 *   « reschedule » (même heure, un autre jour) ou « queue » (après le dernier de la liste).
 *
 * Résultat : ['status' => 'queued', 'entry' => QueueEntry]
 *          | ['status' => 'late', 'appointment' => Appointment, 'reschedule_at' => ?CarbonImmutable, 'queue' => ?array]
 *          | ['status' => 'rescheduled', 'appointment' => Appointment]
 */
class QueueCheckInService
{
    public const CHOICE_RESCHEDULE = 'reschedule';

    public const CHOICE_QUEUE = 'queue';

    public function __construct(
        private QueueService $queueService,
    ) {}

    /** Rendez-vous du jour à enregistrer, retrouvé par le jeton de suivi (SMS) ou le téléphone. */
    public function findTodayAppointment(Salon $salon, ?string $trackingToken, ?string $phone): Appointment
    {
        $query = Appointment::query()
            ->where('salon_id', $salon->id)
            ->whereBetween('scheduled_at', [now()->startOfDay(), now()->endOfDay()])
            ->with(['service', 'client', 'staff']);

        if ($trackingToken !== null && $trackingToken !== '') {
            $appointment = (clone $query)->where('tracking_token', $trackingToken)->first();
        } elseif ($phone !== null && $phone !== '') {
            $client = Client::query()
                ->where('salon_id', $salon->id)
                ->where('phone', IvoryCoastPhone::normalize($phone))
                ->first();

            $appointment = $client === null ? null : (clone $query)
                ->where('client_id', $client->id)
                ->whereIn('status', [AppointmentStatus::Pending, AppointmentStatus::Confirmed, AppointmentStatus::InProgress])
                ->orderBy('scheduled_at')
                ->first();
        } else {
            $appointment = null;
        }

        if ($appointment === null) {
            throw new QueueException('Aucun rendez-vous aujourd\'hui pour ce numéro dans ce salon.', 404, 'appointment_not_found');
        }

        return $appointment;
    }

    public function checkIn(Appointment $appointment, Salon $salon): array
    {
        $existing = $this->activeEntryFor($appointment);

        if ($existing !== null) {
            return ['status' => 'queued', 'entry' => $existing];
        }

        $this->assertCheckInPossible($appointment);

        if ($this->queueService->isLate($appointment, $salon)) {
            $queue = $this->queueService->previewLateJoin($appointment);

            return [
                'status' => 'late',
                'appointment' => $appointment,
                'reschedule_at' => $this->queueService->nextSameTimeSlot($appointment),
                'queue' => $queue !== null && $queue['fits_before_closing'] ? $queue : null,
            ];
        }

        $entry = DB::transaction(function () use ($appointment): QueueEntry {
            $locked = $this->lock($appointment);
            $existing = $this->activeEntryFor($locked);

            return $existing ?? $this->createEntry($locked, QueueEntrySource::Appointment, CarbonImmutable::instance($locked->scheduled_at));
        });

        return ['status' => 'queued', 'entry' => $entry];
    }

    public function chooseLate(Appointment $appointment, Salon $salon, string $choice): array
    {
        $existing = $this->activeEntryFor($appointment);

        if ($existing !== null) {
            return ['status' => 'queued', 'entry' => $existing];
        }

        $this->assertCheckInPossible($appointment);

        if (! $this->queueService->isLate($appointment, $salon)) {
            // Plus en retard (horloge, double appel) : enregistrement normal.
            return $this->checkIn($appointment, $salon);
        }

        return match ($choice) {
            self::CHOICE_RESCHEDULE => $this->reschedule($appointment),
            self::CHOICE_QUEUE => $this->joinAfterLast($appointment),
            default => throw new QueueException('Choix invalide.', 422, 'invalid_choice'),
        };
    }

    private function reschedule(Appointment $appointment): array
    {
        $updated = DB::transaction(function () use ($appointment): Appointment {
            $locked = $this->lock($appointment);
            $slot = $this->queueService->nextSameTimeSlot($locked);

            if ($slot === null) {
                throw new QueueException(
                    'Aucun jour disponible à la même heure dans les 30 prochains jours. Prenez un nouveau rendez-vous.',
                    409,
                    'no_same_time_slot',
                );
            }

            $locked->update([
                'rescheduled_from' => $locked->rescheduled_from ?? $locked->scheduled_at,
                'scheduled_at' => $slot,
                'status' => AppointmentStatus::Confirmed,
                'checked_in_at' => null,
            ]);

            return $locked->refresh()->load(['service', 'client', 'staff', 'salon']);
        });

        $reminderAt = $updated->scheduled_at->copy()->subHour();

        if ($reminderAt->isFuture()) {
            SendAppointmentReminderSms::dispatch($updated)->delay($reminderAt);
        }

        return ['status' => 'rescheduled', 'appointment' => $updated];
    }

    private function joinAfterLast(Appointment $appointment): array
    {
        $entry = DB::transaction(function () use ($appointment): QueueEntry {
            $locked = $this->lock($appointment);
            $existing = $this->activeEntryFor($locked);

            if ($existing !== null) {
                return $existing;
            }

            $preview = $this->queueService->previewLateJoin($locked);

            if ($preview === null || ! $preview['fits_before_closing']) {
                throw new QueueException(
                    'La file est complète jusqu\'à la fermeture. Choisissez de reprogrammer votre rendez-vous.',
                    409,
                    'queue_full',
                );
            }

            return $this->createEntry($locked, QueueEntrySource::Late, CarbonImmutable::now());
        });

        return ['status' => 'queued', 'entry' => $entry];
    }

    private function createEntry(Appointment $appointment, QueueEntrySource $source, CarbonImmutable $priorityAt): QueueEntry
    {
        $now = now();

        $appointment->update(['checked_in_at' => $now]);

        return QueueEntry::query()->create([
            'salon_id' => $appointment->salon_id,
            'client_id' => $appointment->client_id,
            'user_id' => $appointment->user_id,
            'service_id' => $appointment->service_id,
            'appointment_id' => $appointment->id,
            'source' => $source,
            'status' => QueueEntryStatus::Waiting,
            // La position est calculée à la lecture (QueueService) ; colonne historique conservée.
            'position' => 0,
            'priority_at' => $priorityAt,
            'arrived_at' => $now,
            'tracking_token' => Str::random(32),
        ])->load(['service', 'client', 'staff', 'salon']);
    }

    private function assertCheckInPossible(Appointment $appointment): void
    {
        if (! in_array($appointment->status, [AppointmentStatus::Pending, AppointmentStatus::Confirmed], true)) {
            throw new QueueException('Ce rendez-vous ne peut plus être enregistré.', 409, 'appointment_closed');
        }

        if (! $appointment->scheduled_at->isToday()) {
            throw new QueueException('Ce rendez-vous n\'est pas prévu aujourd\'hui.', 409, 'appointment_not_today');
        }
    }

    private function activeEntryFor(Appointment $appointment): ?QueueEntry
    {
        return QueueEntry::query()
            ->where('appointment_id', $appointment->id)
            ->whereIn('status', [QueueEntryStatus::Waiting, QueueEntryStatus::Called, QueueEntryStatus::InService])
            ->with(['service', 'client', 'staff', 'salon'])
            ->first();
    }

    /** Verrou sur le coiffeur (comme la réservation) puis relecture du rendez-vous. */
    private function lock(Appointment $appointment): Appointment
    {
        User::query()->whereKey($appointment->user_id)->lockForUpdate()->first();

        return Appointment::query()->whereKey($appointment->id)->lockForUpdate()->firstOrFail()->load(['service', 'client', 'staff']);
    }
}
