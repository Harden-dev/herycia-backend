<?php

namespace Tests\Concerns;

use App\Enums\SalonStaffRole;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Salon;
use App\Models\Service;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Salon\SalonDefaultScheduleService;

trait CreatesBookableSalon
{
    protected function createBookableSalon(array $salonAttributes = [], bool $withOnlineBooking = true): array
    {
        $plan = $withOnlineBooking
            ? Plan::factory()->free()->create(['has_online_booking' => true])
            : Plan::factory()->free()->withoutOnlineBooking()->create();

        $salon = Salon::factory()->create(array_merge([
            'is_active' => true,
            'slug' => 'salon-koffi-cocody',
            'whatsapp_number' => '2250101020304',
        ], $salonAttributes));

        Subscription::query()->create([
            'salon_id' => $salon->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Trial,
            'is_trial' => true,
            'started_at' => null,
            'ends_at' => null,
            'trial_ends_at' => null,
        ]);

        app(SalonDefaultScheduleService::class)->seedForSalon($salon->id);

        $stylist = User::factory()->create([
            'salon_id' => $salon->id,
            'role' => SalonStaffRole::Stylist,
            'is_active' => true,
            'name' => 'Koffi',
        ]);

        $service = Service::query()->create([
            'salon_id' => $salon->id,
            'name' => 'Coupe homme',
            'duration_min' => 30,
            'price' => 2000,
            'is_active' => true,
        ]);

        return compact('salon', 'plan', 'stylist', 'service');
    }
}
