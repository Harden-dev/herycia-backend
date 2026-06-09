<?php

namespace App\Http\Resources\V1\Queue;

use App\Data\Queue\QueueEntryListItemData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin QueueEntryListItemData */
class QueueEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var QueueEntryListItemData $entry */
        $entry = $this->resource;

        return [
            'id' => $entry->id,
            'position' => $entry->position,
            'client_name' => $entry->clientName,
            'service_name' => $entry->serviceName,
            'wait_minutes' => $entry->waitMinutes,
            'status' => $entry->status,
        ];
    }
}
