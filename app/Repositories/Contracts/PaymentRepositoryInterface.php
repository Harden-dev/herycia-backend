<?php

namespace App\Repositories\Contracts;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PaymentRepositoryInterface
{
    public function create(array $data): Payment;

    public function findByIdForSalon(string $id, string $salonId): ?Payment;

    public function getPaginatedBySalon(
        string $salonId,
        int $perPage = 15,
        ?\DateTimeInterface $from = null,
        ?\DateTimeInterface $to = null,
        ?PaymentMethod $method = null,
        ?PaymentStatus $status = null,
    ): LengthAwarePaginator;

    public function sumPaidAmountBySalonInRange(
        string $salonId,
        \DateTimeInterface $from,
        \DateTimeInterface $to,
    ): int;

    public function countPaidBySalonInRange(
        string $salonId,
        \DateTimeInterface $from,
        \DateTimeInterface $to,
    ): int;

    public function existsPaidForAppointment(string $appointmentId, ?string $exceptPaymentId = null): bool;
}
