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

        if (! is_string($phone) || $phone === '' || ! is_string($password) || strlen($password) < 12) {
            $this->command?->error(
                'Super admin non créé : définir SUPER_ADMIN_PHONE et SUPER_ADMIN_PASSWORD (12 caractères minimum) dans .env.'
            );

            return;
        }

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
