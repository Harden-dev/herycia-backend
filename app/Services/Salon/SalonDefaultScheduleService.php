<?php

namespace App\Services\Salon;

use App\Repositories\Contracts\SalonScheduleRepositoryInterface;

class SalonDefaultScheduleService
{
    public function __construct(
        private SalonScheduleRepositoryInterface $scheduleRepository,
    ) {}

    public function seedForSalon(string $salonId): void
    {
        for ($day = 0; $day <= 6; $day++) {
            $isSunday = $day === 0;

            $this->scheduleRepository->create([
                'salon_id' => $salonId,
                'day_of_week' => $day,
                'opens_at' => $isSunday ? null : '08:00:00',
                'closes_at' => $isSunday ? null : '20:00:00',
                'is_closed' => $isSunday,
            ]);
        }
    }
}
