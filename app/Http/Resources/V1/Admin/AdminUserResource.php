<?php

namespace App\Http\Resources\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class AdminUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'salon_id' => $this->salon_id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'role' => $this->role?->value,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'salon' => $this->whenLoaded('salon', fn () => [
                'id' => $this->salon->id,
                'name' => $this->salon->name,
                'slug' => $this->salon->slug,
                'city' => $this->salon->city,
            ]),
        ];
    }
}
