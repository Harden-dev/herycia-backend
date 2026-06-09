<?php

namespace App\Data\Queue;

readonly class QueueEntryListItemData
{
    public function __construct(
        public string $id,
        public int $position,
        public string $clientName,
        public string $serviceName,
        public int $waitMinutes,
        public string $status,
    ) {}
}
