<?php

namespace App\Support\Admin;

use App\Models\Salon;
use App\Models\Subscription;

class AdminSalonPresenter
{
    public static function latestSubscription(Salon $salon): ?Subscription
    {
        if ($salon->relationLoaded('subscriptions')) {
            return $salon->subscriptions->first();
        }

        return $salon->subscriptions()->with('plan')->latest('created_at')->first();
    }
}
