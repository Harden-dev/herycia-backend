<?php

namespace App\Data\Booking;

use Carbon\Carbon;

readonly class BookingSlotData
{
    public function __construct(
        public Carbon $scheduledAt,
    ) {}
}
