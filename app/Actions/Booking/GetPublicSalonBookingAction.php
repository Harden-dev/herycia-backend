<?php

namespace App\Actions\Booking;

use App\Data\Booking\PublicSalonBookingData;
use App\Repositories\Contracts\ServiceRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Booking\PublicBookingAccessService;
use App\Services\PublicLinkService;

class GetPublicSalonBookingAction
{
    public function __construct(
        private PublicBookingAccessService $bookingAccess,
        private ServiceRepositoryInterface $serviceRepository,
        private UserRepositoryInterface $userRepository,
        private PublicLinkService $publicLinkService,
    ) {}

    public function execute(string $slug): PublicSalonBookingData
    {
        $salon = $this->bookingAccess->resolveBookableSalon($slug);

        return new PublicSalonBookingData(
            salon: $salon,
            services: $this->serviceRepository->getActiveBySalonId($salon->id),
            employees: $this->userRepository->getActiveStylistsBySalonId($salon->id),
            bookingLink: $this->publicLinkService->buildBookingLink($salon->slug),
        );
    }
}
