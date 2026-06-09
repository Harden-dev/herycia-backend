<?php

namespace App\Http\Resources\V1\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Payment */
class ClientPaymentResource extends JsonResource
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
        ];
    }
}
