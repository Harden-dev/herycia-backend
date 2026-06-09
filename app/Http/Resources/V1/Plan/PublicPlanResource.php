<?php

namespace App\Http\Resources\V1\Plan;

use App\Support\Plan\PlanFeatureCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Plan */
class PublicPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'tagline' => PlanFeatureCatalog::taglineFor($this->resource),
            'price_fcfa' => $this->price_fcfa,
            'price_label' => number_format($this->price_fcfa, 0, ',', ' ').' F /mois',
            'max_employees' => $this->max_employees,
            'max_employees_label' => PlanFeatureCatalog::employeesLabelFor($this->resource),
            'max_services' => $this->max_services,
            'has_online_booking' => $this->has_online_booking,
            'has_analytics' => $this->has_analytics,
            'features' => PlanFeatureCatalog::featuresFor($this->resource),
        ];
    }
}
