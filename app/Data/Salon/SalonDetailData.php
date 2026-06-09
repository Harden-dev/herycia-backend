<?php

namespace App\Data\Salon;

use App\Models\Salon;
use App\Models\Subscription;

readonly class SalonDetailData
{
    public function __construct(
        public Salon $salon,
        public ?Subscription $subscription,
        public ?string $logoUrl,
    ) {}
}
