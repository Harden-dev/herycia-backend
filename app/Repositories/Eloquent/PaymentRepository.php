<?php

namespace App\Repositories\Eloquent;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PaymentRepository implements PaymentRepositoryInterface
{
    public function __construct(
        protected Payment $paymentModel,
    ) {}

    public function create(array $data): Payment
    {
        return $this->paymentModel->create($data);
    }

    public function findByIdForSalon(string $id, string $salonId): ?Payment
    {
        return $this->paymentModel->newQuery()
            ->where('id', $id)
            ->where('salon_id', $salonId)
            ->first();
    }

    public function getPaginatedBySalon(
        string $salonId,
        int $perPage = 15,
        ?\DateTimeInterface $from = null,
        ?\DateTimeInterface $to = null,
        ?PaymentMethod $method = null,
        ?PaymentStatus $status = null,
    ): LengthAwarePaginator {
        $query = $this->paymentModel->newQuery()
            ->where('salon_id', $salonId)
            ->with(['client', 'appointment.service']);

        if ($from !== null && $to !== null) {
            $query->whereBetween('paid_at', [$from, $to]);
        }

        if ($method !== null) {
            $query->where('method', $method);
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->orderByDesc('paid_at')->paginate($perPage);
    }

    public function sumPaidAmountBySalonInRange(
        string $salonId,
        \DateTimeInterface $from,
        \DateTimeInterface $to,
    ): int {
        return (int) $this->paymentModel->newQuery()
            ->where('salon_id', $salonId)
            ->where('status', PaymentStatus::Paid)
            ->whereBetween('paid_at', [$from, $to])
            ->sum('amount');
    }

    public function countPaidBySalonInRange(
        string $salonId,
        \DateTimeInterface $from,
        \DateTimeInterface $to,
    ): int {
        return $this->paymentModel->newQuery()
            ->where('salon_id', $salonId)
            ->where('status', PaymentStatus::Paid)
            ->whereBetween('paid_at', [$from, $to])
            ->count();
    }

    public function existsPaidForAppointment(string $appointmentId, ?string $exceptPaymentId = null): bool
    {
        $query = $this->paymentModel->newQuery()
            ->where('appointment_id', $appointmentId)
            ->where('status', PaymentStatus::Paid);

        if ($exceptPaymentId !== null) {
            $query->where('id', '!=', $exceptPaymentId);
        }

        return $query->exists();
    }
}
