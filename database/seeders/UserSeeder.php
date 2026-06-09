<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $userRole = Role::where('slug', 'user')->first();
        $adminRole = Role::where('slug', 'admin')->first();
        User::updateOrCreate(
            ['email' => 'john.doe@example.com'],
            [
                'slug' => 'USR-20251107-000000001',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'phone' => '+1234567890',
                'address' => '123 Main Street, New York, NY 10001',
                'profile_picture' => null,
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
                'is_active' => true,
                'role_id' => $userRole?->id,
            ]
        );
        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'slug' => 'USR-20251107-000000002',
                'first_name' => 'Admin',
                'last_name' => 'Admin',
                'phone' => '+1234567890',
                'address' => '123 Main Street, New York, NY 10001',
                'profile_picture' => null,
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
                'is_active' => true,
                'role_id' => $adminRole?->id,
            ]
        );
    }
}

