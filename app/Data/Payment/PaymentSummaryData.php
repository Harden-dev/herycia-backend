<?php

namespace App\Data\Payment;

readonly class PaymentSummaryData
{
    public function __construct(
        public PaymentPeriodSummaryData $day,
        public PaymentPeriodSummaryData $week,
        public PaymentPeriodSummaryData $month,
    ) {}
}
