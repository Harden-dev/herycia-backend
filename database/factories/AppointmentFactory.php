<?php

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Models\Client;
use App\Models\Salon;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Appointment>
 */
class AppointmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'salon_id' => Salon::factory(),
            'client_id' => Client::factory(),
            'user_id' => User::factory(),
            'service_id' => fn (array $attributes) => Service::query()->create([
                'salon_id' => $attributes['salon_id'] instanceof Salon
                    ? $attributes['salon_id']->id
                    : $attributes['salon_id'],
                'name' => 'Coupe',
                'duration_min' => 30,
                'price' => 5000,
                'is_active' => true,
            ])->id,
            'scheduled_at' => fake()->dateTimeBetween('now', '+1 month'),
            'status' => AppointmentStatus::Pending,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
