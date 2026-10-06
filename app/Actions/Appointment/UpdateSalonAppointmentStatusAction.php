<?php

namespace App\Actions\Appointment;

use App\Data\Appointment\UpdateSalonAppointmentStatusData;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Repositories\Contracts\AppointmentRepositoryInterface;
use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Services\Salon\SalonContextService;

class UpdateSalonAppointmentStatusAction
{
    public function __construct(
        private SalonContextService $salonContext,
        private AppointmentRepositoryInterface $appointmentRepository,
        private ClientRepositoryInterface $clientRepository,
    ) {}

    public function execute(string $appointmentId, UpdateSalonAppointmentStatusData $data): Appointment
    {
        $appointment = $this->salonContext->resolveSalonAppointment($appointmentId);

        if ($appointment->status === AppointmentStatus::Cancelled) {
            throw new \RuntimeException('Impossible de modifier un rendez-vous annulé.');
        }

        // Un rendez-vous terminé est figé : les allers-retours Completed → autre → Completed
        // incrémentaient total_visits à chaque passage (audit M3).
        if ($appointment->status === AppointmentStatus::Completed && $data->status !== AppointmentStatus::Completed) {
            throw new \RuntimeException('Impossible de modifier un rendez-vous terminé.');
        }

        $previousStatus = $appointment->status;

        $this->appointmentRepository->update($appointment, [
            'status' => $data->status,
        ]);

        if ($data->status === AppointmentStatus::Completed && $previousStatus !== AppointmentStatus::Completed) {
            $client = $this->salonContext->resolveSalonClient($appointment->client_id);
            $this->clientRepository->update($client, [
                'total_visits' => $client->total_visits + 1,
                'last_visit_at' => $appointment->scheduled_at,
            ]);
        }

        return $appointment->refresh()->load(['client', 'staff', 'service']);
    }
}
