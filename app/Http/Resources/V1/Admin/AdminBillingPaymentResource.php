<?php

namespace App\Http\Resources\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\SubscriptionPayment */
class AdminBillingPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'method' => $this->method?->value,
            'status' => $this->status?->value,
            'reference' => $this->reference,
            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'salon' => $this->whenLoaded('salon', fn () => [
                'id' => $this->salon->id,
                'name' => $this->salon->name,
                'city' => $this->salon->city,
            ]),
            'plan' => $this->whenLoaded('plan', fn () => [
                'id' => $this->plan->id,
                'name' => $this->plan->name,
                'code' => $this->plan->code,
            ]),
            'subscription' => $this->whenLoaded('subscription', fn () => [
                'id' => $this->subscription->id,
                'status' => $this->subscription->status?->value,
            ]),
        ];
    }
}
