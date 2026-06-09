<?php

namespace App\Http\Resources\V1\Admin;

use App\Support\Admin\AdminSalonPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Salon */
class AdminSalonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $subscription = AdminSalonPresenter::latestSubscription($this->resource);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'city' => $this->city,
            'phone' => $this->phone,
            'whatsapp_number' => $this->whatsapp_number,
            'is_active' => $this->is_active,
            'is_suspended' => $this->isSuspended(),
            'plan_name' => $subscription?->plan?->name,
            'plan_code' => $subscription?->plan?->code,
            'subscription_status' => $subscription?->status?->value,
            'subscription_ends_at' => $subscription?->ends_at?->toDateString(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
