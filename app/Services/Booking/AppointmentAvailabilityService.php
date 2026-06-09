<?php

namespace App\Services\Booking;

use App\Models\Appointment;
use App\Repositories\Contracts\AppointmentRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AppointmentAvailabilityService
{
    public function __construct(
        private AppointmentRepositoryInterface $appointmentRepository,
    ) {}

    public function overlaps(Carbon $startA, int $durationMinutesA, Carbon $startB, int $durationMinutesB): bool
    {
        $endA = $startA->copy()->addMinutes($durationMinutesA);
        $endB = $startB->copy()->addMinutes($durationMinutesB);

        return $startA->lt($endB) && $startB->lt($endA);
    }

    public function hasStaffConflict(
        string $salonId,
        string $userId,
        Carbon $start,
        int $durationMinutes,
        Collection $blockingAppointments,
    ): bool {
        foreach ($blockingAppointments as $appointment) {
            if ($appointment->user_id !== $userId) {
                continue;
            }

            $existingDuration = $appointment->service?->duration_min ?? 30;

            if ($this->overlaps($start, $durationMinutes, $appointment->scheduled_at, $existingDuration)) {
                return true;
            }
        }

        return false;
    }

    /** @return Collection<int, Appointment> */
    public function getBlockingAppointments(
        string $salonId,
        Carbon $from,
        Carbon $to,
        ?string $userId = null,
    ): Collection {
        return $this->appointmentRepository->getBlockingBySalonInRange($salonId, $from, $to, $userId);
    }
}
