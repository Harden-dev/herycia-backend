<?php

namespace App\Actions\Booking;

use App\Models\Appointment;
use App\Repositories\Contracts\AppointmentRepositoryInterface;
use App\Services\Booking\PublicBookingException;

class GetPublicAppointmentTrackingAction
{
    public function __construct(
        private AppointmentRepositoryInterface $appointmentRepository,
    ) {}

    public function execute(string $trackingToken): Appointment
    {
        $appointment = $this->appointmentRepository->findByTrackingTokenWithRelations($trackingToken);

        if ($appointment === null) {
            throw new PublicBookingException('Rendez-vous introuvable.', 404, 'appointment_not_found');
        }

        return $appointment;
    }
}
