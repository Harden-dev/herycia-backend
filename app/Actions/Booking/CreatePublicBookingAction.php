<?php

namespace App\Actions\Booking;

use App\Data\Booking\CreatePublicBookingData;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\User;
use App\Repositories\Contracts\AppointmentRepositoryInterface;
use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Repositories\Contracts\ServiceRepositoryInterface;
use App\Services\Booking\PublicBookingAccessService;
use App\Services\Booking\PublicBookingException;
use App\Services\Booking\StaffAvailabilityService;
use App\Support\IvoryCoastPhone;
use Illuminate\Support\Facades\DB;

class CreatePublicBookingAction
{
    public function __construct(
        private PublicBookingAccessService $bookingAccess,
        private ServiceRepositoryInterface $serviceRepository,
        private ClientRepositoryInterface $clientRepository,
        private AppointmentRepositoryInterface $appointmentRepository,
        private StaffAvailabilityService $staffAvailabilityService,
    ) {}

    public function execute(string $slug, CreatePublicBookingData $data): Appointment
    {
        $salon = $this->bookingAccess->resolveBookableSalon($slug);
        $phone = IvoryCoastPhone::normalize($data->clientPhone);

        if (! IvoryCoastPhone::isValid($phone)) {
            throw new PublicBookingException('Numéro de téléphone invalide.', 422, 'invalid_phone');
        }

        if ($data->scheduledAt->lte(now())) {
            throw new PublicBookingException('La date du rendez-vous doit être dans le futur.', 422, 'invalid_schedule');
        }

        $maxDaysAhead = (int) config('salono.booking_max_days_ahead', 90);

        if ($data->scheduledAt->gt(now()->addDays($maxDaysAhead)->endOfDay())) {
            throw new PublicBookingException(
                "Les réservations sont possibles jusqu'à {$maxDaysAhead} jours à l'avance.",
                422,
                'invalid_schedule',
            );
        }

        $service = $this->serviceRepository->findActiveByIdForSalon($data->serviceId, $salon->id);

        if ($service === null) {
            throw new PublicBookingException('Service invalide pour ce salon.', 422, 'invalid_service');
        }

        // Pré-contrôle hors transaction (réponse rapide si le créneau est déjà pris).
        $this->staffAvailabilityService->resolveStylistForBooking(
            $salon->id,
            $data->userId,
            $data->scheduledAt,
            $service,
        );

        return DB::transaction(function () use ($salon, $data, $phone, $service): Appointment {
            // Verrou sur le coiffeur puis re-vérification : deux réservations simultanées
            // ne peuvent plus obtenir le même créneau (audit H5).
            User::query()->whereKey($data->userId)->lockForUpdate()->first();

            $stylist = $this->staffAvailabilityService->resolveStylistForBooking(
                $salon->id,
                $data->userId,
                $data->scheduledAt,
                $service,
            );

            $client = $this->clientRepository->findByPhoneForSalon($phone, $salon->id);

            // Un client existant n'est jamais modifié depuis la page publique : n'importe qui
            // connaissant un numéro pourrait sinon renommer la fiche dans le CRM du salon (audit H5).
            if ($client === null) {
                $client = $this->clientRepository->create([
                    'salon_id' => $salon->id,
                    'name' => $data->clientName,
                    'phone' => $phone,
                    'whatsapp_id' => $phone,
                    'total_visits' => 0,
                    'last_visit_at' => null,
                    'created_at' => now(),
                ]);
            }

            $appointment = $this->appointmentRepository->create([
                'salon_id' => $salon->id,
                'client_id' => $client->id,
                'user_id' => $stylist->id,
                'service_id' => $service->id,
                'scheduled_at' => $data->scheduledAt,
                'status' => AppointmentStatus::Confirmed,
                'notes' => null,
            ]);

            return $appointment->load(['client', 'staff', 'service']);
        });
    }
}
