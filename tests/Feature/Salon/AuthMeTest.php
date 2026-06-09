<?php

namespace Tests\Feature\Salon;

use App\Enums\SalonStaffRole;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Salon;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthMeTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_authenticated_user_profile(): void
    {
        $plan = Plan::factory()->free()->create();
        $salon = Salon::factory()->create();
        $user = User::factory()->create([
            'salon_id' => $salon->id,
            'role' => SalonStaffRole::Admin,
        ]);
        Subscription::query()->create([
            'salon_id' => $salon->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Trial,
            'is_trial' => true,
            'started_at' => null,
            'ends_at' => null,
            'trial_ends_at' => null,
        ]);

        $token = JWTAuth::fromUser($user);

        $response = $this->getJson('/api/v1/auth/me', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.role', SalonStaffRole::Admin->value)
            ->assertJsonPath('data.salon.id', $salon->id)
            ->assertJsonPath('data.subscription.status', SubscriptionStatus::Trial->value)
            ->assertJsonPath('data.subscription.plan.code', 'free');
    }

    public function test_returns_unauthorized_without_token(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertUnauthorized()
            ->assertJsonPath('success', false);
    }
}
