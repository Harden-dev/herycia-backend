<?php

namespace Tests\Concerns;

use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Salon;
use App\Models\Subscription;

trait CreatesSalonSubscription
{
    protected function createActiveSubscription(Salon $salon, ?Plan $plan = null): Subscription
    {
        $plan ??= Plan::factory()->free()->create();

        return Subscription::query()->create([
            'salon_id' => $salon->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Trial,
            'is_trial' => true,
            'started_at' => null,
            'ends_at' => null,
            'trial_ends_at' => null,
        ]);
    }
}
