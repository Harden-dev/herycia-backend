<?php

namespace App\Http\Resources\V1\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Client */
class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'whatsapp_id' => $this->whatsapp_id,
            'total_visits' => $this->total_visits,
            'last_visit_at' => $this->last_visit_at?->toIso8601String(),
        ];
    }
}
