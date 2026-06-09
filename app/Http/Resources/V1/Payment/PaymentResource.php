<?php

namespace App\Http\Resources\V1\Payment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Payment */
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'appointment_id' => $this->appointment_id,
            'amount' => $this->amount,
            'method' => $this->method?->value,
            'status' => $this->status?->value,
            'mobile_money_ref' => $this->mobile_money_ref,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'client' => $this->whenLoaded('client', fn () => [
                'id' => $this->client?->id,
                'name' => $this->client?->name,
                'phone' => $this->client?->phone,
            ]),
            'appointment' => $this->whenLoaded('appointment', fn () => [
                'id' => $this->appointment?->id,
                'scheduled_at' => $this->appointment?->scheduled_at?->toIso8601String(),
                'service' => $this->appointment?->relationLoaded('service') ? [
                    'id' => $this->appointment?->service?->id,
                    'name' => $this->appointment?->service?->name,
                ] : null,
            ]),
        ];
    }
}
