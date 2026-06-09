<?php

namespace Database\Seeders;

use App\Enums\SalonStaffRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $phone = config('salono.super_admin.phone');
        $password = config('salono.super_admin.password');

        User::query()->updateOrCreate(
            ['phone' => $phone],
            [
                'salon_id' => null,
                'name' => config('salono.super_admin.name'),
                'email' => config('salono.super_admin.email'),
                'password' => Hash::make($password),
                'role' => SalonStaffRole::SuperAdmin,
                'is_active' => true,
            ],
        );

        $this->command?->info("Super admin créé : {$phone}");
    }
}
