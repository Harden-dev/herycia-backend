<?php

namespace Database\Factories;

use App\Enums\SalonStaffRole;
use App\Models\Salon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'salon_id' => Salon::factory(),
            'name' => fake()->name(),
            'email' => fake()->optional()->safeEmail(),
            'phone' => '225'.fake()->unique()->numerify('##########'),
            'password' => static::$password ??= Hash::make('password123'),
            'role' => SalonStaffRole::Admin,
            'is_active' => true,
        ];
    }
}
