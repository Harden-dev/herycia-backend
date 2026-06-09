<?php

namespace App\Http\Resources\V1\Dashboard;

use App\Data\Dashboard\DashboardActivityItemData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DashboardActivityItemData */
class DashboardActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var DashboardActivityItemData $activity */
        $activity = $this->resource;

        return [
            'id' => $activity->id,
            'type' => $activity->type,
            'message' => $activity->message,
            'created_at' => $activity->createdAt,
        ];
    }
}
