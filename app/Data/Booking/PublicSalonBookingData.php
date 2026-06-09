<?php

namespace App\Data\Booking;

use App\Models\Salon;
use Illuminate\Support\Collection;

readonly class PublicSalonBookingData
{
    /** @param Collection<int, \App\Models\Service> $services */
    /** @param Collection<int, \App\Models\User> $employees */
    public function __construct(
        public Salon $salon,
        public Collection $services,
        public Collection $employees,
        public string $bookingLink,
    ) {}
}
