<?php

namespace Tests\Feature\Salon;

use App\Enums\SalonStaffRole;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Salon;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSalonSubscription;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class SubscriptionMiddlewareTest extends TestCase
{
    use CreatesSalonSubscription;
    use RefreshDatabase;

    public function test_expired_subscription_blocks_protected_routes(): void
    {
        $salon = Salon::factory()->create();
        $plan = Plan::factory()->free()->create();
        Subscription::query()->create([
            'salon_id' => $salon->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Trial,
            'is_trial' => true,
            'trial_ends_at' => now()->subDay(),
        ]);
        $user = User::factory()->create([
            'salon_id' => $salon->id,
            'role' => SalonStaffRole::Receptionist,
        ]);
        $token = JWTAuth::fromUser($user);

        $response = $this->getJson('/api/v1/clients', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertForbidden()
            ->assertJsonPath('code', 'subscription_expired')
            ->assertJsonPath('success', false);
    }

    public function test_active_subscription_allows_protected_routes(): void
    {
        $salon = Salon::factory()->create();
        $this->createActiveSubscription($salon);
        $user = User::factory()->create([
            'salon_id' => $salon->id,
            'role' => SalonStaffRole::Receptionist,
        ]);
        $token = JWTAuth::fromUser($user);

        $response = $this->getJson('/api/v1/clients', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_auth_me_works_without_active_subscription(): void
    {
        $salon = Salon::factory()->create();
        $plan = Plan::factory()->free()->create();
        Subscription::query()->create([
            'salon_id' => $salon->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Expired,
            'is_trial' => false,
            'ends_at' => now()->subMonth(),
        ]);
        $user = User::factory()->create([
            'salon_id' => $salon->id,
            'role' => SalonStaffRole::Admin,
        ]);
        $token = JWTAuth::fromUser($user);

        $response = $this->getJson('/api/v1/auth/me', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);
    }
}
