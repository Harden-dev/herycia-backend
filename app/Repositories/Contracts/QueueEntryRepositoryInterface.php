<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface QueueEntryRepositoryInterface
{
    /** @return Collection<int, \App\Models\QueueEntry> */
    public function getWaitingBySalon(string $salonId, int $limit = 5): Collection;

    public function countWaitingBySalon(string $salonId): int;

    public function countArrivalsOnDate(string $salonId, \DateTimeInterface $date): int;
}
