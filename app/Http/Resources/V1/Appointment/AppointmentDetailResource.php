<?php

namespace App\Http\Resources\V1\Appointment;

use App\Http\Resources\V1\Client\ClientPaymentResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Appointment */
class AppointmentDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'status' => $this->status?->value,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'client' => $this->whenLoaded('client', fn () => [
                'id' => $this->client?->id,
                'name' => $this->client?->name,
                'phone' => $this->client?->phone,
                'whatsapp_id' => $this->client?->whatsapp_id,
            ]),
            'staff' => $this->whenLoaded('staff', fn () => [
                'id' => $this->staff?->id,
                'name' => $this->staff?->name,
                'role' => $this->staff?->role?->value,
            ]),
            'service' => $this->whenLoaded('service', fn () => [
                'id' => $this->service?->id,
                'name' => $this->service?->name,
                'duration_min' => $this->service?->duration_min,
                'price' => $this->service?->price,
            ]),
            'payments' => ClientPaymentResource::collection($this->whenLoaded('payments')),
        ];
    }
}
