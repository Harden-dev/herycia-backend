<?php

namespace Tests\Feature\Subscription;

use App\Enums\SalonStaffRole;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Salon;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class SalonSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_subscription_with_usage(): void
    {
        $salon = Salon::factory()->create();
        $plan = Plan::factory()->basic()->create();
        Subscription::query()->create([
            'salon_id' => $salon->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Trial,
            'is_trial' => true,
        ]);
        $admin = User::factory()->create([
            'salon_id' => $salon->id,
            'role' => SalonStaffRole::Admin,
        ]);
        $token = JWTAuth::fromUser($admin);

        $response = $this->getJson('/api/v1/subscription', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.subscription.plan.code', 'basic')
            ->assertJsonPath('data.usage.active_employees', 1)
            ->assertJsonPath('data.usage.max_employees', 5);
    }

    public function test_admin_can_simulate_subscription_payment(): void
    {
        config(['salono.simulate_subscription_payments' => true]);

        $salon = Salon::factory()->create();
        $plan = Plan::factory()->basic()->create();
        $subscription = Subscription::query()->create([
            'salon_id' => $salon->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Trial,
            'is_trial' => true,
        ]);
        $admin = User::factory()->create([
            'salon_id' => $salon->id,
            'role' => SalonStaffRole::Admin,
        ]);
        $token = JWTAuth::fromUser($admin);

        $response = $this->postJson('/api/v1/subscription/simulate-payment', [
            'method' => 'wave',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', SubscriptionStatus::Active->value)
            ->assertJsonPath('data.is_trial', false)
            ->assertJsonPath('data.plan.code', 'basic');

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'status' => SubscriptionStatus::Active->value,
            'is_trial' => false,
        ]);

        $this->assertDatabaseHas('subscription_payments', [
            'salon_id' => $salon->id,
            'subscription_id' => $subscription->id,
            'plan_id' => $plan->id,
            'amount' => 5000,
            'method' => 'wave',
            'status' => 'paid',
        ]);

        $this->assertDatabaseCount('subscriptions', 1);
    }

    public function test_cannot_resubscribe_same_plan_while_still_active(): void
    {
        config(['salono.simulate_subscription_payments' => true]);

        $salon = Salon::factory()->create();
        $plan = Plan::factory()->basic()->create();
        Subscription::query()->create([
            'salon_id' => $salon->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'is_trial' => false,
            'started_at' => now()->subWeek(),
            'ends_at' => now()->addMonth(),
        ]);
        $admin = User::factory()->create([
            'salon_id' => $salon->id,
            'role' => SalonStaffRole::Admin,
        ]);
        $token = JWTAuth::fromUser($admin);

        $response = $this->postJson('/api/v1/subscription/simulate-payment', [
            'method' => 'wave',
            'plan_code' => 'basic',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('subscription_payments', 0);
    }

    public function test_can_upgrade_to_pro_while_basic_is_active(): void
    {
        config(['salono.simulate_subscription_payments' => true]);

        $salon = Salon::factory()->create();
        $basic = Plan::factory()->basic()->create();
        $pro = Plan::factory()->pro()->create();
        $subscription = Subscription::query()->create([
            'salon_id' => $salon->id,
            'plan_id' => $basic->id,
            'status' => SubscriptionStatus::Active,
            'is_trial' => false,
            'started_at' => now()->subWeek(),
            'ends_at' => now()->addMonth(),
        ]);
        $admin = User::factory()->create([
            'salon_id' => $salon->id,
            'role' => SalonStaffRole::Admin,
        ]);
        $token = JWTAuth::fromUser($admin);

        $response = $this->postJson('/api/v1/subscription/simulate-payment', [
            'method' => 'orange_money',
            'plan_code' => 'pro',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.plan.code', 'pro');

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'plan_id' => $pro->id,
            'status' => SubscriptionStatus::Active->value,
        ]);

        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertDatabaseCount('subscription_payments', 1);
    }

    public function test_trial_can_subscribe_directly_to_pro(): void
    {
        config(['salono.simulate_subscription_payments' => true]);

        $salon = Salon::factory()->create();
        Plan::factory()->basic()->create();
        $pro = Plan::factory()->pro()->create();
        $subscription = Subscription::query()->create([
            'salon_id' => $salon->id,
            'plan_id' => $pro->id,
            'status' => SubscriptionStatus::Trial,
            'is_trial' => true,
            'trial_ends_at' => now()->addDays(3),
        ]);
        $admin = User::factory()->create([
            'salon_id' => $salon->id,
            'role' => SalonStaffRole::Admin,
        ]);
        $token = JWTAuth::fromUser($admin);

        $response = $this->postJson('/api/v1/subscription/simulate-payment', [
            'method' => 'wave',
            'plan_code' => 'pro',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.plan.code', 'pro')
            ->assertJsonPath('data.is_trial', false);
    }
}
