<?php

namespace App\Actions\Appointment;

use App\Data\Appointment\CreateSalonAppointmentData;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Repositories\Contracts\AppointmentRepositoryInterface;
use App\Services\Salon\SalonContextService;

class CreateSalonAppointmentAction
{
    public function __construct(
        private SalonContextService $salonContext,
        private AppointmentRepositoryInterface $appointmentRepository,
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

        return $this->appointmentRepository->create([
            'salon_id' => $salon->id,
            'client_id' => $data->clientId,
            'user_id' => $data->userId,
            'service_id' => $data->serviceId,
            'scheduled_at' => $data->scheduledAt,
            'status' => AppointmentStatus::Pending,
            'notes' => $data->notes,
        ]);
    }
}
