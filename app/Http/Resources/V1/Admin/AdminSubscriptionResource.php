<?php

namespace App\Http\Resources\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Subscription */
class AdminSubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'salon_id' => $this->salon_id,
            'plan_id' => $this->plan_id,
            'status' => $this->status?->value,
            'started_at' => $this->started_at?->toDateString(),
            'ends_at' => $this->ends_at?->toDateString(),
            'trial_ends_at' => $this->trial_ends_at?->toDateString(),
            'is_trial' => $this->is_trial,
            'created_at' => $this->created_at?->toIso8601String(),
            'salon' => $this->whenLoaded('salon', fn () => [
                'id' => $this->salon->id,
                'name' => $this->salon->name,
                'slug' => $this->salon->slug,
                'city' => $this->salon->city,
            ]),
            'plan' => $this->whenLoaded('plan', fn () => [
                'id' => $this->plan->id,
                'name' => $this->plan->name,
                'code' => $this->plan->code,
                'price_fcfa' => $this->plan->price_fcfa,
            ]),
        ];
    }
}
