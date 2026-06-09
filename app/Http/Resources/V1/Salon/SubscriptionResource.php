<?php

namespace App\Http\Resources\V1\Salon;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Subscription */
class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status?->value,
            'is_trial' => $this->is_trial,
            'plan' => $this->whenLoaded('plan', fn () => [
                'code' => $this->plan->code,
                'name' => $this->plan->name,
                'price_fcfa' => $this->plan->price_fcfa,
                'max_employees' => $this->plan->max_employees,
                'max_services' => $this->plan->max_services,
                'has_online_booking' => $this->plan->has_online_booking,
                'has_analytics' => $this->plan->has_analytics,
            ]),
            'trial_ends_at' => $this->trial_ends_at?->toDateString(),
            'ends_at' => $this->ends_at?->toDateString(),
        ];
    }
}
