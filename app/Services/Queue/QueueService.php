<?php

namespace App\Services\Queue;

use App\Enums\AppointmentStatus;
use App\Enums\QueueEntrySource;
use App\Enums\QueueEntryStatus;
use App\Models\Appointment;
use App\Models\QueueEntry;
use App\Models\Salon;
use App\Models\User;
use App\Repositories\Contracts\SalonScheduleRepositoryInterface;
use App\Services\Booking\AppointmentAvailabilityService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * File d'attente par coiffeur : alimente {@see QueueSimulator} depuis la base.
 */
class QueueService
{
    private const DEFAULT_DURATION = 30;

    private const PREVIEW_ID = '__preview__';

    /** Jours explorés pour trouver « la même heure, un autre jour ». */
    private const RESCHEDULE_SEARCH_DAYS = 30;

    public function __construct(
        private QueueSimulator $simulator,
        private SalonScheduleRepositoryInterface $scheduleRepository,
        private AppointmentAvailabilityService $availabilityService,
    ) {}

    /** Le client est-il au-delà de la tolérance de retard pour ce rendez-vous ? */
    public function isLate(Appointment $appointment, Salon $salon, ?CarbonImmutable $now = null): bool
    {
        $now ??= CarbonImmutable::now();
        $limit = CarbonImmutable::instance($appointment->scheduled_at)
            ->addMinutes($salon->late_tolerance_minutes ?? 15);

        return $now->greaterThan($limit);
    }

    /**
     * Entrées actives du jour d'un coiffeur, avec position et heure estimée, dans l'ordre de passage.
     *
     * @return list<array{entry: QueueEntry, position: int|null, estimated_start_at: CarbonImmutable|null}>
     */
    public function boardForStylist(string $salonId, string $stylistId, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $entries = $this->activeEntries($salonId, $stylistId, $now);
        $estimates = $this->estimate($entries, $salonId, $stylistId, $now);

        $rows = $entries->map(fn (QueueEntry $entry) => [
            'entry' => $entry,
            'position' => $estimates[$entry->id]['position'] ?? null,
            'estimated_start_at' => $estimates[$entry->id]['estimated_start_at'] ?? null,
        ])->all();

        // Le client en cours d'abord, puis l'ordre de passage estimé.
        usort($rows, function (array $a, array $b): int {
            $aInService = $a['entry']->status === QueueEntryStatus::InService;
            $bInService = $b['entry']->status === QueueEntryStatus::InService;

            if ($aInService !== $bInService) {
                return $aInService ? -1 : 1;
            }

            return ($a['position'] ?? PHP_INT_MAX) <=> ($b['position'] ?? PHP_INT_MAX);
        });

        return array_values($rows);
    }

    /**
     * Position et heure estimée d'une entrée.
     *
     * @return array{position: int|null, people_ahead: int|null, estimated_start_at: CarbonImmutable|null}
     */
    public function estimateForEntry(QueueEntry $entry, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();

        if (! $entry->isActive() || $entry->user_id === null) {
            return ['position' => null, 'people_ahead' => null, 'estimated_start_at' => null];
        }

        if ($entry->status === QueueEntryStatus::InService) {
            return ['position' => 0, 'people_ahead' => 0, 'estimated_start_at' => CarbonImmutable::instance($entry->started_at ?? $now)];
        }

        $entries = $this->activeEntries($entry->salon_id, $entry->user_id, $now);
        $estimates = $this->estimate($entries, $entry->salon_id, $entry->user_id, $now);
        $estimate = $estimates[$entry->id] ?? null;

        return [
            'position' => $estimate['position'] ?? null,
            'people_ahead' => isset($estimate['position']) ? $estimate['position'] - 1 : null,
            'estimated_start_at' => $estimate['estimated_start_at'] ?? null,
        ];
    }

    /**
     * Aperçu « passer après le dernier de la liste » pour un retardataire, sans rien enregistrer.
     *
     * @return array{position: int, people_ahead: int, estimated_start_at: CarbonImmutable, fits_before_closing: bool}|null
     */
    public function previewLateJoin(Appointment $appointment, ?CarbonImmutable $now = null): ?array
    {
        return $this->previewFloatingJoin(
            $appointment->salon_id,
            $appointment->user_id,
            $appointment->service?->duration_min ?? self::DEFAULT_DURATION,
            $now,
            excludeAppointmentId: $appointment->id,
        );
    }

    /**
     * Aperçu d'un nouveau client placé après le dernier (retardataire ou sans rendez-vous).
     *
     * @return array{position: int, people_ahead: int, estimated_start_at: CarbonImmutable, fits_before_closing: bool}|null
     */
    public function previewFloatingJoin(
        string $salonId,
        string $stylistId,
        int $durationMinutes,
        ?CarbonImmutable $now = null,
        ?string $excludeAppointmentId = null,
    ): ?array {
        $now ??= CarbonImmutable::now();
        $entries = $this->activeEntries($salonId, $stylistId, $now);

        $estimates = $this->estimate(
            $entries,
            $salonId,
            $stylistId,
            $now,
            extraFloating: ['id' => self::PREVIEW_ID, 'priority_at' => $now, 'duration' => $durationMinutes],
            excludeAppointmentId: $excludeAppointmentId,
        );

        $preview = $estimates[self::PREVIEW_ID] ?? null;

        if ($preview === null) {
            return null;
        }

        return [
            'position' => $preview['position'],
            'people_ahead' => $preview['position'] - 1,
            'estimated_start_at' => $preview['estimated_start_at'],
            'fits_before_closing' => $this->fitsBeforeClosing($salonId, $preview['estimated_end_at']),
        ];
    }

    /**
     * Options d'un client sans rendez-vous : estimation pour chaque coiffeur actif, et le meilleur
     * choix pour « premier coiffeur disponible » (passage le plus tôt avant la fermeture).
     *
     * @param  Collection<int, User>  $stylists
     * @return array{stylists: list<array{stylist: User, position: int, people_ahead: int, estimated_start_at: CarbonImmutable, available: bool}>, best_stylist_id: string|null}
     */
    public function walkInOptions(string $salonId, Collection $stylists, int $durationMinutes, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $options = [];
        $best = null;

        foreach ($stylists as $stylist) {
            $preview = $this->previewFloatingJoin($salonId, $stylist->id, $durationMinutes, $now);

            if ($preview === null) {
                continue;
            }

            $options[] = [
                'stylist' => $stylist,
                'position' => $preview['position'],
                'people_ahead' => $preview['people_ahead'],
                'estimated_start_at' => $preview['estimated_start_at'],
                'available' => $preview['fits_before_closing'],
            ];

            if ($preview['fits_before_closing']
                && ($best === null || $preview['estimated_start_at']->lessThan($best['at']))) {
                $best = ['id' => $stylist->id, 'at' => $preview['estimated_start_at']];
            }
        }

        return ['stylists' => $options, 'best_stylist_id' => $best['id'] ?? null];
    }

    /**
     * Prochain jour où le même coiffeur est libre à la même heure, salon ouvert.
     */
    public function nextSameTimeSlot(Appointment $appointment, ?CarbonImmutable $now = null): ?CarbonImmutable
    {
        $now ??= CarbonImmutable::now();
        $original = CarbonImmutable::instance($appointment->rescheduled_from ?? $appointment->scheduled_at);
        $duration = $appointment->service?->duration_min ?? self::DEFAULT_DURATION;
        $maxDaysAhead = (int) config('salono.booking_max_days_ahead', 90);

        for ($offset = 1; $offset <= min(self::RESCHEDULE_SEARCH_DAYS, $maxDaysAhead); $offset++) {
            $candidate = $now->startOfDay()->addDays($offset)->setTime((int) $original->format('H'), (int) $original->format('i'));

            if (! $this->isWithinOpeningHours($appointment->salon_id, $candidate, $duration)) {
                continue;
            }

            $start = Carbon::instance($candidate);
            $blocking = $this->availabilityService
                ->getBlockingAppointments($appointment->salon_id, $start->copy()->subHours(4), $start->copy()->addHours(4), $appointment->user_id)
                ->reject(fn (Appointment $other) => $other->id === $appointment->id);

            if (! $this->availabilityService->hasStaffConflict(
                $appointment->salon_id,
                $appointment->user_id,
                $start,
                $duration,
                $blocking,
            )) {
                return $candidate;
            }
        }

        return null;
    }

    /** @return Collection<int, QueueEntry> */
    private function activeEntries(string $salonId, string $stylistId, CarbonImmutable $now): Collection
    {
        return QueueEntry::query()
            ->where('salon_id', $salonId)
            ->where('user_id', $stylistId)
            ->whereIn('status', [QueueEntryStatus::Waiting, QueueEntryStatus::Called, QueueEntryStatus::InService])
            ->where('arrived_at', '>=', $now->startOfDay())
            ->with(['client', 'service', 'appointment'])
            ->get();
    }

    /**
     * @param  Collection<int, QueueEntry>  $entries
     * @param  array{id: string, priority_at: CarbonImmutable, duration: int}|null  $extraFloating
     * @return array<string, array{position: int, estimated_start_at: CarbonImmutable, estimated_end_at: CarbonImmutable}>
     */
    private function estimate(
        Collection $entries,
        string $salonId,
        string $stylistId,
        CarbonImmutable $now,
        ?array $extraFloating = null,
        ?string $excludeAppointmentId = null,
    ): array {
        $inService = null;
        $called = [];
        $anchored = [];
        $floating = [];

        foreach ($entries as $entry) {
            $duration = $entry->service?->duration_min ?? self::DEFAULT_DURATION;

            if ($entry->status === QueueEntryStatus::InService) {
                $inService = [
                    'id' => $entry->id,
                    'started_at' => CarbonImmutable::instance($entry->started_at ?? $now),
                    'duration' => $duration,
                ];
            } elseif ($entry->status === QueueEntryStatus::Called) {
                $called[] = ['id' => $entry->id, 'duration' => $duration, 'called_at' => $entry->called_at];
            } elseif ($entry->source !== null && ! $entry->source->isAnchored()) {
                $floating[] = ['id' => $entry->id, 'priority_at' => CarbonImmutable::instance($entry->priority_at ?? $entry->arrived_at), 'duration' => $duration];
            } else {
                $anchored[] = ['id' => $entry->id, 'priority_at' => CarbonImmutable::instance($entry->priority_at ?? $entry->arrived_at), 'duration' => $duration];
            }
        }

        usort($called, fn (array $a, array $b) => $a['called_at'] <=> $b['called_at']);

        if ($extraFloating !== null) {
            $floating[] = $extraFloating;
        }

        $reservations = Appointment::query()
            ->where('salon_id', $salonId)
            ->where('user_id', $stylistId)
            ->whereIn('status', [AppointmentStatus::Pending, AppointmentStatus::Confirmed])
            ->whereNull('checked_in_at')
            ->whereBetween('scheduled_at', [$now, $now->endOfDay()])
            ->when($excludeAppointmentId !== null, fn ($q) => $q->where('id', '!=', $excludeAppointmentId))
            ->with('service')
            ->get()
            ->map(fn (Appointment $a) => [
                'at' => CarbonImmutable::instance($a->scheduled_at),
                'duration' => $a->service?->duration_min ?? self::DEFAULT_DURATION,
            ])
            ->all();

        return $this->simulator->simulate(
            $now,
            $inService,
            array_map(fn (array $c) => ['id' => $c['id'], 'duration' => $c['duration']], $called),
            $anchored,
            $floating,
            $reservations,
        );
    }

    private function fitsBeforeClosing(string $salonId, CarbonImmutable $end): bool
    {
        $schedule = $this->scheduleRepository->findForSalonAndDay($salonId, $end->dayOfWeek);

        if ($schedule === null || $schedule->is_closed || $schedule->closes_at === null) {
            return true;
        }

        return $end->lessThanOrEqualTo($end->setTimeFromTimeString($schedule->closes_at));
    }

    private function isWithinOpeningHours(string $salonId, CarbonImmutable $start, int $duration): bool
    {
        $schedule = $this->scheduleRepository->findForSalonAndDay($salonId, $start->dayOfWeek);

        if ($schedule === null || $schedule->is_closed || $schedule->opens_at === null || $schedule->closes_at === null) {
            return false;
        }

        $opens = $start->setTimeFromTimeString($schedule->opens_at);
        $closes = $start->setTimeFromTimeString($schedule->closes_at);

        return $start->greaterThanOrEqualTo($opens) && $start->addMinutes($duration)->lessThanOrEqualTo($closes);
    }
}
