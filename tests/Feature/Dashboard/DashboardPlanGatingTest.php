<?php

namespace Tests\Feature\Dashboard;

use App\Enums\SalonStaffRole;
use App\Models\Plan;
use App\Models\Salon;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSalonSubscription;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class DashboardPlanGatingTest extends TestCase
{
    use CreatesSalonSubscription;
    use RefreshDatabase;

    public function test_basic_plan_cannot_access_revenue_stats(): void
    {
        $salon = Salon::factory()->create();
        $plan = Plan::factory()->basic()->create();
        Subscription::query()->create([
            'salon_id' => $salon->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'is_trial' => false,
            'ends_at' => now()->addMonth(),
        ]);
        $user = User::factory()->create([
            'salon_id' => $salon->id,
            'role' => SalonStaffRole::Admin,
        ]);
        $token = JWTAuth::fromUser($user);

        $response = $this->getJson('/api/v1/dashboard/revenue-stats?period=month', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertForbidden()
            ->assertJsonPath('code', 'subscription_feature_denied');
    }

    public function test_premium_plan_can_access_revenue_stats(): void
    {
        $salon = Salon::factory()->create();
        $plan = Plan::factory()->premium()->create();
        Subscription::query()->create([
            'salon_id' => $salon->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'is_trial' => false,
            'ends_at' => now()->addMonth(),
        ]);
        $user = User::factory()->create([
            'salon_id' => $salon->id,
            'role' => SalonStaffRole::Admin,
        ]);
        $token = JWTAuth::fromUser($user);

        $response = $this->getJson('/api/v1/dashboard/revenue-stats?period=month', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_overview_remains_available_for_basic_plan(): void
    {
        $salon = Salon::factory()->create();
        $this->createActiveSubscription($salon, Plan::factory()->basic()->create());
        $user = User::factory()->create([
            'salon_id' => $salon->id,
            'role' => SalonStaffRole::Admin,
        ]);
        $token = JWTAuth::fromUser($user);

        $response = $this->getJson('/api/v1/dashboard/overview', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);
    }
}
