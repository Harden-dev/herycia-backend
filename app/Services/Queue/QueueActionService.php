<?php

namespace App\Services\Queue;

use App\Enums\AppointmentStatus;
use App\Enums\QueueEntryStatus;
use App\Enums\SalonStaffRole;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\QueueEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Actions du personnel sur la file : appeler, démarrer, terminer, absent, retirer.
 * Le rendez-vous lié suit le même état (en cours, terminé, absent, annulé).
 */
class QueueActionService
{
    public const ACTION_CALL = 'call';

    public const ACTION_START = 'start';

    public const ACTION_DONE = 'done';

    public const ACTION_NO_SHOW = 'no-show';

    public const ACTION_CANCEL = 'cancel';

    public const ACTIONS = [
        self::ACTION_CALL,
        self::ACTION_START,
        self::ACTION_DONE,
        self::ACTION_NO_SHOW,
        self::ACTION_CANCEL,
    ];

    /** Statuts de départ autorisés pour chaque action. */
    private const ALLOWED_FROM = [
        self::ACTION_CALL => [QueueEntryStatus::Waiting],
        self::ACTION_START => [QueueEntryStatus::Waiting, QueueEntryStatus::Called],
        self::ACTION_DONE => [QueueEntryStatus::InService],
        self::ACTION_NO_SHOW => [QueueEntryStatus::Waiting, QueueEntryStatus::Called],
        self::ACTION_CANCEL => [QueueEntryStatus::Waiting, QueueEntryStatus::Called],
    ];

    public function apply(string $salonId, string $entryId, string $action): QueueEntry
    {
        if (! in_array($action, self::ACTIONS, true)) {
            throw new QueueException('Action inconnue.', 422, 'invalid_action');
        }

        return DB::transaction(function () use ($salonId, $entryId, $action): QueueEntry {
            $entry = QueueEntry::query()
                ->where('salon_id', $salonId)
                ->whereKey($entryId)
                ->lockForUpdate()
                ->first();

            if ($entry === null) {
                throw new QueueException('Entrée de file introuvable.', 404, 'queue_entry_not_found');
            }

            if (! in_array($entry->status, self::ALLOWED_FROM[$action], true)) {
                throw new QueueException('Action impossible dans l\'état actuel de ce client.', 409, 'invalid_transition');
            }

            if ($action === self::ACTION_START) {
                $this->assertStylistFree($entry);
            }

            $now = now();

            match ($action) {
                self::ACTION_CALL => $entry->update(['status' => QueueEntryStatus::Called, 'called_at' => $now]),
                self::ACTION_START => $entry->update([
                    'status' => QueueEntryStatus::InService,
                    'called_at' => $entry->called_at ?? $now,
                    'started_at' => $now,
                ]),
                self::ACTION_DONE => $entry->update(['status' => QueueEntryStatus::Done, 'done_at' => $now]),
                self::ACTION_NO_SHOW, self::ACTION_CANCEL => $entry->update(['status' => QueueEntryStatus::Cancelled, 'done_at' => $now]),
            };

            $this->syncAppointment($entry, $action);

            return $entry->refresh()->load(['client', 'service', 'staff', 'appointment']);
        });
    }

    /**
     * Confie un client en attente (ou appelé) à un autre coiffeur actif, par exemple quand le sien
     * s'absente. Il reprend l'attente chez le nouveau coiffeur ; le rendez-vous lié suit.
     */
    public function reassign(string $salonId, string $entryId, string $stylistId): QueueEntry
    {
        return DB::transaction(function () use ($salonId, $entryId, $stylistId): QueueEntry {
            $stylist = User::query()
                ->where('salon_id', $salonId)
                ->whereKey($stylistId)
                ->where('role', SalonStaffRole::Stylist)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if ($stylist === null) {
                throw new QueueException('Coiffeur invalide pour ce salon.', 422, 'invalid_stylist');
            }

            $entry = QueueEntry::query()->where('salon_id', $salonId)->whereKey($entryId)->lockForUpdate()->first();

            if ($entry === null) {
                throw new QueueException('Entrée de file introuvable.', 404, 'queue_entry_not_found');
            }

            if (! in_array($entry->status, [QueueEntryStatus::Waiting, QueueEntryStatus::Called], true)) {
                throw new QueueException('Seul un client en attente peut changer de coiffeur.', 409, 'invalid_transition');
            }

            if ($entry->user_id !== $stylist->id) {
                $entry->update([
                    'user_id' => $stylist->id,
                    'status' => QueueEntryStatus::Waiting,
                    'called_at' => null,
                    // Nouvelle file, nouveau calcul : le SMS « bientôt » pourra repartir.
                    'soon_notified_at' => null,
                ]);

                if ($entry->appointment_id !== null) {
                    Appointment::query()->whereKey($entry->appointment_id)->update(['user_id' => $stylist->id]);
                }
            }

            return $entry->refresh()->load(['client', 'service', 'staff', 'appointment']);
        });
    }

    /** Un coiffeur ne sert qu'un client à la fois. */
    private function assertStylistFree(QueueEntry $entry): void
    {
        $busy = QueueEntry::query()
            ->where('salon_id', $entry->salon_id)
            ->where('user_id', $entry->user_id)
            ->where('status', QueueEntryStatus::InService)
            ->whereKeyNot($entry->id)
            ->exists();

        if ($busy) {
            throw new QueueException('Ce coiffeur a déjà un client en cours. Terminez-le d\'abord.', 409, 'stylist_busy');
        }
    }

    private function syncAppointment(QueueEntry $entry, string $action): void
    {
        if ($entry->appointment_id === null) {
            return;
        }

        $appointment = Appointment::query()->whereKey($entry->appointment_id)->lockForUpdate()->first();

        if ($appointment === null) {
            return;
        }

        $status = match ($action) {
            self::ACTION_START => AppointmentStatus::InProgress,
            self::ACTION_DONE => AppointmentStatus::Completed,
            self::ACTION_NO_SHOW => AppointmentStatus::NoShow,
            self::ACTION_CANCEL => AppointmentStatus::Cancelled,
            default => null,
        };

        if ($status === null || $appointment->status === $status) {
            return;
        }

        $wasCompleted = $appointment->status === AppointmentStatus::Completed;
        $appointment->update(['status' => $status]);

        // Même règle que le changement de statut back-office : une visite comptée une seule fois.
        if ($status === AppointmentStatus::Completed && ! $wasCompleted) {
            $client = Client::query()->whereKey($appointment->client_id)->lockForUpdate()->first();
            $client?->update([
                'total_visits' => $client->total_visits + 1,
                'last_visit_at' => $appointment->scheduled_at,
            ]);
        }
    }
}
