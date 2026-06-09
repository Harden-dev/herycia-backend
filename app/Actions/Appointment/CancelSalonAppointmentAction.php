<?php

namespace App\Actions\Appointment;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Repositories\Contracts\AppointmentRepositoryInterface;
use App\Services\Salon\SalonContextService;

class CancelSalonAppointmentAction
{
    public function __construct(
        private SalonContextService $salonContext,
        private AppointmentRepositoryInterface $appointmentRepository,
    ) {}

    public function execute(string $appointmentId): Appointment
    {
        $appointment = $this->salonContext->resolveSalonAppointment($appointmentId);

        if ($appointment->status === AppointmentStatus::Cancelled) {
            throw new \RuntimeException('Ce rendez-vous est déjà annulé.');
        }

        if ($appointment->status === AppointmentStatus::Completed) {
            throw new \RuntimeException('Impossible d\'annuler un rendez-vous terminé.');
        }

        $this->appointmentRepository->update($appointment, [
            'status' => AppointmentStatus::Cancelled,
        ]);

        return $appointment->refresh()->load(['client', 'staff', 'service']);
    }
}
