<?php

namespace App\Data\Payment;

readonly class PaymentPeriodSummaryData
{
    public function __construct(
        public string $from,
        public string $to,
        public int $total,
        public int $count,
    ) {}
}
