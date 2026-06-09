<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        Plan::updateOrCreate(
            ['code' => 'basic'],
            [
                'name' => 'Basic',
                'price_fcfa' => 5000,
                'max_employees' => 5,
                'max_services' => 50,
                'has_online_booking' => true,
                'has_analytics' => false,
                'has_multi_branch' => false,
                'is_archived' => false,
            ],
        );

        Plan::updateOrCreate(
            ['code' => 'pro'],
            [
                'name' => 'Pro',
                'price_fcfa' => 15000,
                'max_employees' => null,
                'max_services' => 200,
                'has_online_booking' => true,
                'has_analytics' => true,
                'has_multi_branch' => false,
                'is_archived' => false,
            ],
        );

        Plan::query()->whereIn('code', ['premium', 'enterprise'])->update(['is_archived' => true]);
    }
}
