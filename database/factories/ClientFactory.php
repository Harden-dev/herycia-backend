<?php

namespace Database\Factories;

use App\Models\Salon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Client>
 */
class ClientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'salon_id' => Salon::factory(),
            'name' => fake()->name(),
            'phone' => '22507'.fake()->unique()->numerify('########'),
            'whatsapp_id' => null,
            'total_visits' => fake()->numberBetween(0, 20),
            'last_visit_at' => fake()->optional()->dateTimeBetween('-1 year'),
            'created_at' => fake()->dateTimeBetween('-1 year'),
        ];
    }
}
