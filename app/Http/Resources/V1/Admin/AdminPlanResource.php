<?php

namespace App\Http\Resources\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Plan */
class AdminPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'price_fcfa' => $this->price_fcfa,
            'max_employees' => $this->max_employees,
            'max_services' => $this->max_services,
            'has_online_booking' => $this->has_online_booking,
            'has_analytics' => $this->has_analytics,
            'has_multi_branch' => $this->has_multi_branch,
            'is_archived' => $this->is_archived,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
