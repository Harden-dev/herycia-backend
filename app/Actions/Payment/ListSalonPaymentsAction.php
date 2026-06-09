<?php

namespace App\Actions\Payment;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Services\Salon\SalonContextService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListSalonPaymentsAction
{
    public function __construct(
        private SalonContextService $salonContext,
        private PaymentRepositoryInterface $paymentRepository,
    ) {}

    public function execute(
        int $perPage = 15,
        ?string $date = null,
        ?string $from = null,
        ?string $to = null,
        ?PaymentMethod $method = null,
        ?PaymentStatus $status = null,
    ): LengthAwarePaginator {
        $salon = $this->salonContext->resolveAuthenticatedSalon();

        [$rangeFrom, $rangeTo] = $this->resolveDateRange($date, $from, $to);

        return $this->paymentRepository->getPaginatedBySalon(
            $salon->id,
            $perPage,
            $rangeFrom,
            $rangeTo,
            $method,
            $status ?? PaymentStatus::Paid,
        );
    }

    /** @return array{0: ?Carbon, 1: ?Carbon} */
    private function resolveDateRange(?string $date, ?string $from, ?string $to): array
    {
        if ($from !== null && $to !== null) {
            return [
                Carbon::parse($from)->startOfDay(),
                Carbon::parse($to)->endOfDay(),
            ];
        }

        if ($date !== null) {
            $day = Carbon::parse($date);

            return [$day->copy()->startOfDay(), $day->copy()->endOfDay()];
        }

        return [null, null];
    }
}
