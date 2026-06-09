<?php

namespace App\Http\Resources\V1\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Service */
class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'duration_min' => $this->duration_min,
            'price' => $this->price,
            'is_active' => $this->is_active,
        ];
    }
}
