<?php

namespace App\Http\Resources\V1\Dashboard;

use App\Data\Dashboard\DashboardMetricData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DashboardMetricData */
class DashboardMetricResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var DashboardMetricData $metric */
        $metric = $this->resource;

        return [
            'value' => $metric->value,
            'trend' => $metric->trend,
            'trend_percent' => $metric->trendPercent,
        ];
    }
}
