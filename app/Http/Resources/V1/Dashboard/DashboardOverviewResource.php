<?php

namespace App\Http\Resources\V1\Dashboard;

use App\Data\Dashboard\DashboardOverviewData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DashboardOverviewData */
class DashboardOverviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var DashboardOverviewData $overview */
        $overview = $this->resource;

        return [
            'appointments_today' => new DashboardMetricResource($overview->appointmentsToday),
            'revenue_today' => new DashboardMetricResource($overview->revenueToday),
            'active_clients' => new DashboardMetricResource($overview->activeClients),
            'queue_waiting' => new DashboardMetricResource($overview->queueWaiting),
        ];
    }
}
