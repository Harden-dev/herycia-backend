<?php

namespace App\Repositories\Eloquent;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Repositories\Contracts\AppointmentRepositoryInterface;
use Illuminate\Support\Collection;

class AppointmentRepository implements AppointmentRepositoryInterface
{
    public function __construct(
        protected Appointment $appointmentModel,
    ) {}

    public function create(array $data): Appointment
    {
        return $this->appointmentModel->create($data);
    }

    public function findByIdForSalon(string $id, string $salonId): ?Appointment
    {
        return $this->appointmentModel->newQuery()
            ->where('id', $id)
            ->where('salon_id', $salonId)
            ->first();
    }

    public function findByIdForSalonWithRelations(string $id, string $salonId): ?Appointment
    {
        return $this->appointmentModel->newQuery()
            ->where('id', $id)
            ->where('salon_id', $salonId)
            ->with(['client', 'staff', 'service', 'payments'])
            ->first();
    }

    public function findByTrackingTokenWithRelations(string $trackingToken): ?Appointment
    {
        return $this->appointmentModel->newQuery()
            ->where('tracking_token', $trackingToken)
            ->with(['client', 'staff', 'service', 'salon'])
            ->first();
    }

    public function getBySalonInRange(
        string $salonId,
        \DateTimeInterface $from,
        \DateTimeInterface $to,
        ?string $userId = null,
    ): Collection {
        $query = $this->appointmentModel->newQuery()
            ->where('salon_id', $salonId)
            ->whereBetween('scheduled_at', [$from, $to])
            ->with(['client', 'staff', 'service']);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query->orderBy('scheduled_at')->get();
    }

    public function getBlockingBySalonInRange(
        string $salonId,
        \DateTimeInterface $from,
        \DateTimeInterface $to,
        ?string $userId = null,
    ): Collection {
        $query = $this->appointmentModel->newQuery()
            ->where('salon_id', $salonId)
            ->whereBetween('scheduled_at', [$from, $to])
            ->whereNotIn('status', [
                AppointmentStatus::Cancelled,
                AppointmentStatus::NoShow,
            ])
            ->with('service');

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query->get();
    }

    public function update(Appointment $appointment, array $data): bool
    {
        return $appointment->update($data);
    }
}
