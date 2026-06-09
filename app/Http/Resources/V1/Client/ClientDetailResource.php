<?php

namespace App\Http\Resources\V1\Client;

use App\Data\Client\ClientDetailData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ClientDetailData */
class ClientDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var ClientDetailData $detail */
        $detail = $this->resource;
        $client = $detail->client;

        return [
            'id' => $client->id,
            'name' => $client->name,
            'phone' => $client->phone,
            'whatsapp_id' => $client->whatsapp_id,
            'total_visits' => $client->total_visits,
            'last_visit_at' => $client->last_visit_at?->toIso8601String(),
            'appointments' => ClientAppointmentResource::collection($detail->appointments),
            'payments' => ClientPaymentResource::collection($detail->payments),
        ];
    }
}
