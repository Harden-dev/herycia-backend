<?php

namespace App\Services\Booking;

use App\Enums\SalonStaffRole;
use App\Models\Service;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class StaffAvailabilityService
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private AppointmentAvailabilityService $availabilityService,
    ) {}

    /** @return Collection<int, User> */
    public function getAvailableStylists(string $salonId, Carbon $scheduledAt, Service $service): Collection
    {
        $stylists = $this->userRepository->getActiveStylistsBySalonId($salonId);
        $rangeStart = $scheduledAt->copy()->subHours(4);
        $rangeEnd = $scheduledAt->copy()->addHours(4);
        $blockingAppointments = $this->availabilityService->getBlockingAppointments(
            $salonId,
            $rangeStart,
            $rangeEnd,
        );

        return $stylists->filter(function (User $stylist) use ($salonId, $scheduledAt, $service, $blockingAppointments): bool {
            return ! $this->availabilityService->hasStaffConflict(
                $salonId,
                $stylist->id,
                $scheduledAt,
                $service->duration_min,
                $blockingAppointments,
            );
        })->values();
    }

    public function resolveStylistForBooking(
        string $salonId,
        string $userId,
        Carbon $scheduledAt,
        Service $service,
    ): User {
        $stylist = $this->userRepository->findByIdForSalon($userId, $salonId);

        if ($stylist === null || $stylist->role !== SalonStaffRole::Stylist || ! $stylist->is_active) {
            throw new PublicBookingException('Coiffeur invalide pour ce salon.', 422, 'invalid_stylist');
        }

        $available = $this->getAvailableStylists($salonId, $scheduledAt, $service);

        if (! $available->contains('id', $stylist->id)) {
            throw new PublicBookingException('Ce créneau n\'est plus disponible.', 409, 'slot_unavailable');
        }

        return $stylist;
    }
}
