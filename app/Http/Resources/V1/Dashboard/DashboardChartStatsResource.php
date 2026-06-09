<?php

namespace App\Http\Resources\V1\Dashboard;

use App\Data\Dashboard\DashboardChartStatsData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DashboardChartStatsData */
class DashboardChartStatsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var DashboardChartStatsData $stats */
        $stats = $this->resource;

        return [
            'period' => $stats->period,
            'points' => array_map(
                static fn ($point): array => [
                    'label' => $point->label,
                    'value' => $point->value,
                ],
                $stats->points,
            ),
            'total' => $stats->total,
            'trend_percent' => $stats->trendPercent,
        ];
    }
}
