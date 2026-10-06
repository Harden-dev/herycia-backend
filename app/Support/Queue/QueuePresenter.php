<?php

namespace App\Support\Queue;

use App\Models\Appointment;
use App\Models\QueueEntry;
use App\Models\Salon;
use App\Services\PublicLinkService;
use App\Services\Queue\QueueService;
use Carbon\CarbonInterface;

/** Format JSON commun de la file (public et back-office). */
class QueuePresenter
{
    public function __construct(
        private QueueService $queueService,
        private PublicLinkService $links,
    ) {}

    /** Résultat d'un enregistrement ou d'un choix de retardataire. */
    public function checkInResult(array $result, Salon $salon): array
    {
        return match ($result['status']) {
            'queued' => [
                'status' => 'queued',
                'entry' => $this->publicEntry($result['entry']),
            ],
            'late' => [
                'status' => 'late',
                'late_tolerance_minutes' => $salon->late_tolerance_minutes ?? 15,
                'appointment' => $this->appointment($result['appointment']),
                'options' => [
                    'reschedule' => $result['reschedule_at'] !== null
                        ? ['scheduled_at' => $this->iso($result['reschedule_at'])]
                        : null,
                    'queue' => $result['queue'] !== null
                        ? [
                            'position' => $result['queue']['position'],
                            'people_ahead' => $result['queue']['people_ahead'],
                            'estimated_start_at' => $this->iso($result['queue']['estimated_start_at']),
                        ]
                        : null,
                ],
            ],
            'rescheduled' => [
                'status' => 'rescheduled',
                'appointment' => $this->appointment($result['appointment']),
            ],
        };
    }

    /** Suivi public : aucune donnée personnelle hormis le prénom saisi par le client. */
    public function publicEntry(QueueEntry $entry): array
    {
        $estimate = $this->queueService->estimateForEntry($entry);

        return [
            'tracking_token' => $entry->tracking_token,
            'tracking_link' => $entry->tracking_token !== null
                ? $this->links->buildQueueTrackingLink($entry->tracking_token)
                : null,
            'status' => $entry->status->value,
            'source' => $entry->source?->value,
            'position' => $estimate['position'],
            'people_ahead' => $estimate['people_ahead'],
            'estimated_start_at' => $this->iso($estimate['estimated_start_at']),
            'arrived_at' => $this->iso($entry->arrived_at),
            'called_at' => $this->iso($entry->called_at),
            'client' => ['name' => $entry->client?->name],
            'service' => [
                'name' => $entry->service?->name,
                'duration_min' => $entry->service?->duration_min,
            ],
            'stylist' => ['name' => $entry->staff?->name],
            'salon' => ['name' => $entry->salon?->name, 'slug' => $entry->salon?->slug],
        ];
    }

    /** Ligne du tableau back-office. */
    public function boardRow(array $row): array
    {
        /** @var QueueEntry $entry */
        $entry = $row['entry'];

        return [
            'id' => $entry->id,
            'status' => $entry->status->value,
            'source' => $entry->source?->value,
            'position' => $row['position'],
            'estimated_start_at' => $this->iso($row['estimated_start_at']),
            'arrived_at' => $this->iso($entry->arrived_at),
            'called_at' => $this->iso($entry->called_at),
            'started_at' => $this->iso($entry->started_at),
            'client' => [
                'id' => $entry->client?->id,
                'name' => $entry->client?->name,
                'phone' => $entry->client?->phone,
            ],
            'service' => [
                'id' => $entry->service?->id,
                'name' => $entry->service?->name,
                'duration_min' => $entry->service?->duration_min,
            ],
            'appointment' => $entry->appointment !== null
                ? ['id' => $entry->appointment->id, 'scheduled_at' => $this->iso($entry->appointment->scheduled_at)]
                : null,
        ];
    }

    /**
     * Options d'un client sans rendez-vous : estimation par coiffeur et choix « premier disponible ».
     *
     * @param  array{stylists: list<array>, best_stylist_id: string|null}  $options
     */
    public function walkInOptions(array $options): array
    {
        $best = collect($options['stylists'])->firstWhere('stylist.id', $options['best_stylist_id']);

        return [
            'first_available' => $best !== null
                ? [
                    'stylist' => ['id' => $best['stylist']->id, 'name' => $best['stylist']->name],
                    'position' => $best['position'],
                    'estimated_start_at' => $this->iso($best['estimated_start_at']),
                ]
                : null,
            'stylists' => array_map(fn (array $o) => [
                'stylist' => ['id' => $o['stylist']->id, 'name' => $o['stylist']->name],
                'position' => $o['position'],
                'people_ahead' => $o['people_ahead'],
                'estimated_start_at' => $this->iso($o['estimated_start_at']),
                'available' => $o['available'],
            ], $options['stylists']),
        ];
    }

        /** Rendez-vous du jour pas encore arrivé (back-office). */
    public function expectedAppointment(Appointment $appointment, Salon $salon): array
    {
        return [
            'id' => $appointment->id,
            'scheduled_at' => $this->iso($appointment->scheduled_at),
            'status' => $appointment->status->value,
            'is_late' => $this->queueService->isLate($appointment, $salon),
            'client' => [
                'id' => $appointment->client?->id,
                'name' => $appointment->client?->name,
                'phone' => $appointment->client?->phone,
            ],
            'service' => [
                'id' => $appointment->service?->id,
                'name' => $appointment->service?->name,
                'duration_min' => $appointment->service?->duration_min,
            ],
        ];
    }

    private function appointment(Appointment $appointment): array
    {
        return [
            'tracking_token' => $appointment->tracking_token,
            'tracking_link' => $appointment->tracking_token !== null
                ? $this->links->buildTrackingLink($appointment->tracking_token)
                : null,
            'scheduled_at' => $this->iso($appointment->scheduled_at),
            'rescheduled_from' => $this->iso($appointment->rescheduled_from),
            'service' => ['name' => $appointment->service?->name],
            'stylist' => ['name' => $appointment->staff?->name],
            'client' => ['name' => $appointment->client?->name],
        ];
    }

    private function iso(?CarbonInterface $date): ?string
    {
        return $date?->toIso8601String();
    }
}
