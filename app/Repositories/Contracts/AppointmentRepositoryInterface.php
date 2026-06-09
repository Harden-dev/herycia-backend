<?php

namespace App\Repositories\Contracts;

use App\Models\Appointment;
use Illuminate\Support\Collection;

interface AppointmentRepositoryInterface
{
    public function create(array $data): Appointment;

    public function findByIdForSalon(string $id, string $salonId): ?Appointment;

    public function findByIdForSalonWithRelations(string $id, string $salonId): ?Appointment;

    public function findByTrackingTokenWithRelations(string $trackingToken): ?Appointment;

    public function getBySalonInRange(
        string $salonId,
        \DateTimeInterface $from,
        \DateTimeInterface $to,
        ?string $userId = null,
    ): Collection;

    /** @return Collection<int, Appointment> */
    public function getBlockingBySalonInRange(
        string $salonId,
        \DateTimeInterface $from,
        \DateTimeInterface $to,
        ?string $userId = null,
    ): Collection;

    public function update(Appointment $appointment, array $data): bool;
}
