<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Plan>
 */
class PlanFactory extends Factory
{
    public function definition(): array
    {
        $code = fake()->unique()->slug(1);

        return [
            'name' => fake()->words(2, true),
            'code' => $code,
            'price_fcfa' => fake()->numberBetween(0, 50000),
            'max_employees' => 5,
            'max_services' => 20,
            'has_online_booking' => true,
            'has_analytics' => false,
            'has_multi_branch' => false,
            'is_archived' => false,
        ];
    }

    public function free(): static
    {
        return $this->state(fn () => [
            'name' => 'Gratuit',
            'code' => 'free',
            'price_fcfa' => 0,
            'max_employees' => 3,
            'max_services' => 10,
            'has_online_booking' => true,
            'has_analytics' => false,
            'has_multi_branch' => false,
        ]);
    }

    public function basic(): static
    {
        return $this->state(fn () => [
            'name' => 'Basic',
            'code' => 'basic',
            'price_fcfa' => 5000,
            'max_employees' => 5,
            'max_services' => 50,
            'has_online_booking' => true,
            'has_analytics' => false,
            'has_multi_branch' => false,
        ]);
    }

    public function pro(): static
    {
        return $this->state(fn () => [
            'name' => 'Pro',
            'code' => 'pro',
            'price_fcfa' => 15000,
            'max_employees' => null,
            'max_services' => 200,
            'has_online_booking' => true,
            'has_analytics' => true,
            'has_multi_branch' => false,
        ]);
    }

    /** @deprecated Use pro() */
    public function premium(): static
    {
        return $this->pro();
    }

    public function withoutOnlineBooking(): static
    {
        return $this->state(fn () => [
            'has_online_booking' => false,
        ]);
    }
}
