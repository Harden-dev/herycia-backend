<?php

namespace App\Data\Salon;

use App\Models\Salon;
use App\Models\Subscription;
use App\Models\User;

readonly class SalonRegisterOutcome
{
    public function __construct(
        public Salon $salon,
        public User $user,
        public Subscription $subscription,
        public string $accessToken,
        public string $tokenType,
        public int|string $expiresIn,
        public string $expiresAt,
    ) {}
}
