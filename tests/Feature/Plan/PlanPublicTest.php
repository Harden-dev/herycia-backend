<?php

namespace Tests\Feature\Plan;

use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanPublicTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_public_plans_ordered_by_price(): void
    {
        Plan::factory()->pro()->create();
        Plan::factory()->basic()->create();
        Plan::factory()->create([
            'code' => 'enterprise',
            'price_fcfa' => 30000,
            'is_archived' => true,
        ]);

        $response = $this->getJson('/api/v1/plans');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.code', 'basic')
            ->assertJsonPath('data.1.code', 'pro')
            ->assertJsonPath('data.0.price_fcfa', 5000)
            ->assertJsonPath('data.1.price_fcfa', 15000)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'code',
                        'name',
                        'tagline',
                        'price_fcfa',
                        'price_label',
                        'features',
                    ],
                ],
            ]);
    }
}
