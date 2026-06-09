<?php

namespace App\Actions\Queue;

use App\Data\Queue\QueueEntryListItemData;
use App\Models\QueueEntry;
use App\Repositories\Contracts\QueueEntryRepositoryInterface;
use App\Services\Salon\SalonContextService;

class ListSalonQueueAction
{
    public function __construct(
        private SalonContextService $salonContext,
        private QueueEntryRepositoryInterface $queueEntryRepository,
    ) {}

    /** @return list<QueueEntryListItemData> */
    public function execute(int $limit = 5): array
    {
        $salon = $this->salonContext->resolveAuthenticatedSalon();

        return $this->queueEntryRepository
            ->getWaitingBySalon($salon->id, $limit)
            ->map(static function (QueueEntry $entry): QueueEntryListItemData {
                $waitMinutes = $entry->arrived_at !== null
                    ? max(0, (int) $entry->arrived_at->diffInMinutes(now()))
                    : 0;

                return new QueueEntryListItemData(
                    id: $entry->id,
                    position: $entry->position,
                    clientName: $entry->client?->name ?? 'Client',
                    serviceName: $entry->service?->name ?? 'Prestation',
                    waitMinutes: $waitMinutes,
                    status: $entry->status->value,
                );
            })
            ->values()
            ->all();
    }
}
