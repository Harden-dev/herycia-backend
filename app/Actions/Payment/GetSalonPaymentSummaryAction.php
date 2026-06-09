<?php

namespace App\Actions\Payment;

use App\Data\Payment\PaymentPeriodSummaryData;
use App\Data\Payment\PaymentSummaryData;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Services\Salon\SalonContextService;
use Carbon\Carbon;

class GetSalonPaymentSummaryAction
{
    public function __construct(
        private SalonContextService $salonContext,
        private PaymentRepositoryInterface $paymentRepository,
    ) {}

    public function execute(): PaymentSummaryData
    {
        $salon = $this->salonContext->resolveAuthenticatedSalon();
        $now = now();

        return new PaymentSummaryData(
            day: $this->buildPeriodSummary($salon->id, $now->copy()->startOfDay(), $now->copy()->endOfDay()),
            week: $this->buildPeriodSummary($salon->id, $now->copy()->startOfWeek(), $now->copy()->endOfWeek()),
            month: $this->buildPeriodSummary($salon->id, $now->copy()->startOfMonth(), $now->copy()->endOfMonth()),
        );
    }

    private function buildPeriodSummary(string $salonId, Carbon $from, Carbon $to): PaymentPeriodSummaryData
    {
        return new PaymentPeriodSummaryData(
            from: $from->toIso8601String(),
            to: $to->toIso8601String(),
            total: $this->paymentRepository->sumPaidAmountBySalonInRange($salonId, $from, $to),
            count: $this->paymentRepository->countPaidBySalonInRange($salonId, $from, $to),
        );
    }
}
