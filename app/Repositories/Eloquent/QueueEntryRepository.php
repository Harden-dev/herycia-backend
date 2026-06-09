<?php

namespace App\Repositories\Eloquent;

use App\Enums\QueueEntryStatus;
use App\Models\QueueEntry;
use App\Repositories\Contracts\QueueEntryRepositoryInterface;
use Illuminate\Support\Collection;

class QueueEntryRepository implements QueueEntryRepositoryInterface
{
    public function __construct(
        protected QueueEntry $queueEntryModel,
    ) {}

    public function getWaitingBySalon(string $salonId, int $limit = 5): Collection
    {
        return $this->queueEntryModel->newQuery()
            ->where('salon_id', $salonId)
            ->whereIn('status', [QueueEntryStatus::Waiting, QueueEntryStatus::Called])
            ->with(['client', 'service'])
            ->orderBy('position')
            ->limit($limit)
            ->get();
    }

    public function countWaitingBySalon(string $salonId): int
    {
        return $this->queueEntryModel->newQuery()
            ->where('salon_id', $salonId)
            ->where('status', QueueEntryStatus::Waiting)
            ->count();
    }

    public function countArrivalsOnDate(string $salonId, \DateTimeInterface $date): int
    {
        $day = \Carbon\Carbon::parse($date);

        return $this->queueEntryModel->newQuery()
            ->where('salon_id', $salonId)
            ->whereBetween('arrived_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
            ->count();
    }
}
