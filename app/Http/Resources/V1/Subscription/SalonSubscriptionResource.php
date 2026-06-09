<?php

namespace App\Http\Resources\V1\Subscription;

use App\Http\Resources\V1\Salon\SubscriptionResource;
use App\Support\Plan\PlanFeatureCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalonSubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var array{subscription: \App\Models\Subscription, active_employees: int} $payload */
        $payload = $this->resource;
        $subscription = $payload['subscription'];
        $plan = $subscription->plan;

        return [
            'subscription' => new SubscriptionResource($subscription),
            'usage' => [
                'active_employees' => $payload['active_employees'],
                'max_employees' => $plan?->max_employees,
                'max_employees_label' => $plan ? PlanFeatureCatalog::employeesLabelFor($plan) : null,
                'employees_remaining' => $plan?->hasUnlimitedEmployees()
                    ? null
                    : max(0, ($plan->max_employees ?? 0) - $payload['active_employees']),
            ],
            'features' => $plan ? PlanFeatureCatalog::featuresFor($plan) : [],
        ];
    }
}
