<?php

namespace App\Data\Auth;

use App\Models\Subscription;
use App\Models\User;

readonly class MeData
{
    public function __construct(
        public User $user,
        public ?Subscription $subscription,
    ) {}
}
