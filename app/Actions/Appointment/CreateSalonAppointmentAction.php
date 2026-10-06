<?php

namespace App\Actions\Appointment;

use App\Data\Appointment\CreateSalonAppointmentData;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\User;
use App\Services\Booking\AppointmentAvailabilityService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Repositories\Contracts\AppointmentRepositoryInterface;
use App\Services\Salon\SalonContextService;

class CreateSalonAppointmentAction
{
    public function __construct(
        private SalonContextService $salonContext,
        private AppointmentRepositoryInterface $appointmentRepository,
        private AppointmentAvailabilityService $availabilityService,
    ) {}

    public function execute(CreateSalonAppointmentData $data): Appointment
    {
        $salon = $this->salonContext->resolveAuthenticatedSalon();
        $this->salonContext->resolveSalonClient($data->clientId);

        $staff = $this->salonContext->resolveSalonEmployee($data->userId);
        if (! $staff->is_active) {
            throw new \RuntimeException('Le coiffeur sélectionné est inactif.');
        }

        $service = $this->salonContext->resolveSalonService($data->serviceId);
        if (! $service->is_active) {
            throw new \RuntimeException('Le service sélectionné est inactif.');
        }

        return DB::transaction(function () use ($salon, $data, $service): Appointment {
            // Verrou sur le coiffeur + contrôle de chevauchement : évite la double réservation
            // d'un même créneau, y compris face à une réservation publique simultanée (audit M3).
            User::query()->whereKey($data->userId)->lockForUpdate()->first();

            $scheduledAt = Carbon::parse($data->scheduledAt);
            $blocking = $this->availabilityService->getBlockingAppointments(
                $salon->id,
                $scheduledAt->copy()->subHours(4),
                $scheduledAt->copy()->addHours(4),
                $data->userId,
            );

            if ($this->availabilityService->hasStaffConflict(
                $salon->id,
                $data->userId,
                $scheduledAt,
                $service->duration_min,
                $blocking,
            )) {
                throw new \RuntimeException('Ce coiffeur a déjà un rendez-vous sur ce créneau.');
            }

            return $this->appointmentRepository->create([
                'salon_id' => $salon->id,
                'client_id' => $data->clientId,
                'user_id' => $data->userId,
                'service_id' => $data->serviceId,
                'scheduled_at' => $data->scheduledAt,
                'status' => AppointmentStatus::Pending,
                'notes' => $data->notes,
            ]);
        });
    }
}
