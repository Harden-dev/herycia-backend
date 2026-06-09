<?php

namespace App\Repositories\Eloquent;

use App\Models\SalonSchedule;
use App\Repositories\Contracts\SalonScheduleRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class SalonScheduleRepository implements SalonScheduleRepositoryInterface
{
    public function __construct(
        protected SalonSchedule $scheduleModel,
    ) {}

    public function create(array $data): SalonSchedule
    {
        return $this->scheduleModel->create($data);
    }

    public function getBySalonId(string $salonId): Collection
    {
        return $this->scheduleModel->newQuery()
            ->where('salon_id', $salonId)
            ->orderBy('day_of_week')
            ->get();
    }

    public function findForSalonAndDay(string $salonId, int $dayOfWeek): ?SalonSchedule
    {
        return $this->scheduleModel->newQuery()
            ->where('salon_id', $salonId)
            ->where('day_of_week', $dayOfWeek)
            ->first();
    }
}
