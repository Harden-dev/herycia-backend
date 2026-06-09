<?php

namespace App\Repositories\Contracts;

use App\Models\SalonSchedule;
use Illuminate\Database\Eloquent\Collection;

interface SalonScheduleRepositoryInterface
{
    public function create(array $data): SalonSchedule;

    public function getBySalonId(string $salonId): Collection;

    public function findForSalonAndDay(string $salonId, int $dayOfWeek): ?SalonSchedule;
}
