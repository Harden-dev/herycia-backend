<?php

namespace App\Http\Resources\V1\Payment;

use App\Data\Payment\PaymentPeriodSummaryData;
use App\Data\Payment\PaymentSummaryData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PaymentSummaryData */
class PaymentSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var PaymentSummaryData $summary */
        $summary = $this->resource;

        return [
            'day' => $this->period($summary->day),
            'week' => $this->period($summary->week),
            'month' => $this->period($summary->month),
        ];
    }

    /** @return array<string, mixed> */
    private function period(PaymentPeriodSummaryData $period): array
    {
        return [
            'from' => $period->from,
            'to' => $period->to,
            'total' => $period->total,
            'count' => $period->count,
        ];
    }
}
