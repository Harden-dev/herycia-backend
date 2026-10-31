<?php

namespace App\Services\Queue;

use App\Enums\QueueEntrySource;
use App\Enums\QueueEntryStatus;
use App\Enums\SalonStaffRole;
use App\Models\Client;
use App\Models\QueueEntry;
use App\Models\Salon;
use App\Models\Service;
use App\Models\User;
use App\Support\IvoryCoastPhone;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Client sans rendez-vous : il choisit une prestation et un coiffeur (ou « premier disponible »)
 * et est placé après le dernier de la liste, comme un retardataire.
 */
class QueueWalkInService
{
    public function __construct(
        private QueueService $queueService,
    ) {}

    /** @return Collection<int, User> */
    public function activeStylists(Salon $salon): Collection
    {
        return User::query()
            ->where('salon_id', $salon->id)
            ->where('role', SalonStaffRole::Stylist)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function findService(Salon $salon, string $serviceId): Service
    {
        $service = Service::query()
            ->where('salon_id', $salon->id)
            ->where('is_active', true)
            ->whereKey($serviceId)
            ->first();

        if ($service === null) {
            throw new QueueException('Prestation introuvable pour ce salon.', 422, 'invalid_service');
        }

        return $service;
    }

    /** Estimations par coiffeur pour une prestation. */
    public function options(Salon $salon, Service $service): array
    {
        return $this->queueService->walkInOptions(
            $salon->id,
            $this->activeStylists($salon),
            $service->duration_min ?? 30,
        );
    }

    /**
     * Ajoute le client à la file. $stylistId null = premier coiffeur disponible.
     */
    public function join(Salon $salon, Service $service, ?string $stylistId, string $name, string $phone): QueueEntry
    {
        $phone = IvoryCoastPhone::normalize($phone);

        if (! IvoryCoastPhone::isValid($phone)) {
            throw new QueueException('Numéro de téléphone invalide.', 422, 'invalid_phone');
        }

        $existing = $this->activeEntryForPhone($salon, $phone);

        if ($existing !== null) {
            return $existing;
        }

        $stylist = $this->resolveStylist($salon, $service, $stylistId);

        return DB::transaction(function () use ($salon, $service, $stylist, $name, $phone): QueueEntry {
            // Même verrou que la réservation et l'arrivée : un seul calcul de place à la fois par coiffeur.
            User::query()->whereKey($stylist->id)->lockForUpdate()->first();

            $existing = $this->activeEntryForPhone($salon, $phone);

            if ($existing !== null) {
                return $existing;
            }

            $preview = $this->queueService->previewFloatingJoin($salon->id, $stylist->id, $service->duration_min ?? 30);

            if ($preview === null || ! $preview['fits_before_closing']) {
                throw new QueueException(
                    'Ce coiffeur n\'a plus de place aujourd\'hui avant la fermeture.',
                    409,
                    'queue_full',
                );
            }

            // Un client existant n'est jamais renommé depuis une saisie publique.
            $client = Client::query()->where('salon_id', $salon->id)->where('phone', $phone)->first()
                ?? Client::query()->create([
                    'salon_id' => $salon->id,
                    'name' => Str::limit(trim($name), 100, ''),
                    'phone' => $phone,
                    'whatsapp_id' => $phone,
                    'total_visits' => 0,
                    'last_visit_at' => null,
                    'created_at' => now(),
                ]);

            $now = CarbonImmutable::now();

            return QueueEntry::query()->create([
                'salon_id' => $salon->id,
                'client_id' => $client->id,
                'user_id' => $stylist->id,
                'service_id' => $service->id,
                'appointment_id' => null,
                'source' => QueueEntrySource::WalkIn,
                'status' => QueueEntryStatus::Waiting,
                'position' => 0,
                'priority_at' => $now,
                'arrived_at' => $now,
                'tracking_token' => Str::random(32),
            ])->load(['service', 'client', 'staff', 'salon']);
        });
    }

    private function resolveStylist(Salon $salon, Service $service, ?string $stylistId): User
    {
        if ($stylistId !== null) {
            $stylist = $this->activeStylists($salon)->firstWhere('id', $stylistId);

            if ($stylist === null) {
                throw new QueueException('Coiffeur invalide pour ce salon.', 422, 'invalid_stylist');
            }

            return $stylist;
        }

        $options = $this->options($salon, $service);
        $best = $options['best_stylist_id'] !== null
            ? $this->activeStylists($salon)->firstWhere('id', $options['best_stylist_id'])
            : null;

        if ($best === null) {
            throw new QueueException('Plus aucun coiffeur disponible aujourd\'hui avant la fermeture.', 409, 'queue_full');
        }

        return $best;
    }

    /** Un même client n'occupe qu'une place active à la fois dans le salon. */
    private function activeEntryForPhone(Salon $salon, string $phone): ?QueueEntry
    {
        return QueueEntry::query()
            ->where('salon_id', $salon->id)
            ->whereIn('status', [QueueEntryStatus::Waiting, QueueEntryStatus::Called, QueueEntryStatus::InService])
            ->where('arrived_at', '>=', now()->startOfDay())
            ->whereHas('client', fn ($q) => $q->where('phone', $phone))
            ->with(['service', 'client', 'staff', 'salon'])
            ->first();
    }
}
