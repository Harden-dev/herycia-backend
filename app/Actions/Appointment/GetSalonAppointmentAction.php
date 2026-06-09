<?php

namespace App\Actions\Appointment;

use App\Models\Appointment;
use App\Repositories\Contracts\AppointmentRepositoryInterface;
use App\Services\Salon\SalonContextService;

class GetSalonAppointmentAction
{
    public function __construct(
        private SalonContextService $salonContext,
        private AppointmentRepositoryInterface $appointmentRepository,
    ) {}

    public function execute(string $appointmentId): Appointment
    {
        $salon = $this->salonContext->resolveAuthenticatedSalon();
        $appointment = $this->appointmentRepository->findByIdForSalonWithRelations($appointmentId, $salon->id);

        if ($appointment === null) {
            throw new \RuntimeException('Rendez-vous introuvable.');
        }

        return $appointment;
    }
}
