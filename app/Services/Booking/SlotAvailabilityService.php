<?php

namespace App\Services\Booking;

use App\Data\Booking\BookingSlotData;
use App\Models\Service;
use App\Models\User;
use App\Repositories\Contracts\SalonScheduleRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SlotAvailabilityService
{
    private const SLOT_INTERVAL_MINUTES = 30;

    private const MAX_SLOTS = 5;

    private const DAYS_AHEAD = 3;

    public function __construct(
        private SalonScheduleRepositoryInterface $scheduleRepository,
        private UserRepositoryInterface $userRepository,
        private AppointmentAvailabilityService $availabilityService,
    ) {}

    /** @return list<BookingSlotData> */
    public function getAvailableSlots(string $salonId, Service $service): array
    {
        $stylists = $this->userRepository->getActiveStylistsBySalonId($salonId);

        if ($stylists->isEmpty()) {
            return [];
        }

        $rangeStart = now()->startOfMinute();
        $rangeEnd = now()->copy()->addDays(self::DAYS_AHEAD)->endOfDay();
        $blockingAppointments = $this->availabilityService->getBlockingAppointments(
            $salonId,
            $rangeStart,
            $rangeEnd,
        );

        $slots = [];

        for ($dayOffset = 0; $dayOffset < self::DAYS_AHEAD && count($slots) < self::MAX_SLOTS; $dayOffset++) {
            $date = now()->copy()->startOfDay()->addDays($dayOffset);
            $schedule = $this->scheduleRepository->findForSalonAndDay($salonId, $date->dayOfWeek);

            if ($schedule === null || $schedule->is_closed || $schedule->opens_at === null || $schedule->closes_at === null) {
                continue;
            }

            $dayOpen = $date->copy()->setTimeFromTimeString($schedule->opens_at);
            $dayClose = $date->copy()->setTimeFromTimeString($schedule->closes_at)->subMinutes($service->duration_min);

            if ($dayClose->lt($dayOpen)) {
                continue;
            }

            $cursor = $dayOpen->copy();

            while ($cursor->lte($dayClose) && count($slots) < self::MAX_SLOTS) {
                if ($cursor->lte(now())) {
                    $cursor->addMinutes(self::SLOT_INTERVAL_MINUTES);

                    continue;
                }

                if ($this->hasAvailableStylist($stylists, $salonId, $cursor, $service->duration_min, $blockingAppointments)) {
                    $slots[] = new BookingSlotData(scheduledAt: $cursor->copy());
                }

                $cursor->addMinutes(self::SLOT_INTERVAL_MINUTES);
            }
        }

        return $slots;
    }

    /** @param Collection<int, User> $stylists */
    private function hasAvailableStylist(
        Collection $stylists,
        string $salonId,
        Carbon $start,
        int $durationMinutes,
        Collection $blockingAppointments,
    ): bool {
        foreach ($stylists as $stylist) {
            if (! $this->availabilityService->hasStaffConflict(
                $salonId,
                $stylist->id,
                $start,
                $durationMinutes,
                $blockingAppointments,
            )) {
                return true;
            }
        }

        return false;
    }
}
