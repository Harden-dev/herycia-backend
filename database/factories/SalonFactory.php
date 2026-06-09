<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Salon>
 */
class SalonFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->company().' Salon';

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'phone' => fake()->phoneNumber(),
            'whatsapp_number' => fake()->phoneNumber(),
            'city' => fake()->city(),
            'address' => fake()->address(),
            'is_active' => true,
        ];
    }
}
