<?php

namespace Tests\Feature\Admin;

use App\Enums\BillingPaymentMethod;
use App\Enums\BillingPaymentStatus;
use App\Enums\SalonStaffRole;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Salon;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    private function superAdminToken(): string
    {
        $admin = User::factory()->create([
            'salon_id' => null,
            'role' => SalonStaffRole::SuperAdmin,
            'phone' => '2250700000001',
        ]);

        return JWTAuth::fromUser($admin);
    }

    private function authHeaders(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }

    public function test_super_admin_can_get_stats_overview(): void
    {
        $plan = Plan::factory()->free()->create(['price_fcfa' => 10000]);
        $salon = Salon::factory()->create(['city' => 'Abidjan']);
        Subscription::query()->create([
            'salon_id' => $salon->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'is_trial' => false,
            'started_at' => now()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
        ]);

        $token = $this->superAdminToken();

        $response = $this->getJson('/api/v1/admin/stats/overview', $this->authHeaders($token));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.salons_count', 1)
            ->assertJsonPath('data.mrr', 10000);
    }

    public function test_super_admin_can_list_salons(): void
    {
        Salon::factory()->create(['name' => 'Salon Test', 'city' => 'Bouaké']);

        $response = $this->getJson('/api/v1/admin/salons', $this->authHeaders($this->superAdminToken()));

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Salon Test')
            ->assertJsonPath('data.0.city', 'Bouaké');
    }

    public function test_salon_admin_cannot_access_admin_routes(): void
    {
        $salon = Salon::factory()->create();
        $user = User::factory()->create([
            'salon_id' => $salon->id,
            'role' => SalonStaffRole::Admin,
        ]);

        $response = $this->getJson('/api/v1/admin/stats/overview', $this->authHeaders(JWTAuth::fromUser($user)));

        $response->assertForbidden();
    }

    public function test_super_admin_can_suspend_and_deactivate_salon(): void
    {
        $salon = Salon::factory()->create(['is_active' => true]);

        $headers = $this->authHeaders($this->superAdminToken());

        $this->patchJson("/api/v1/admin/salons/{$salon->id}/suspend", [], $headers)
            ->assertOk()
            ->assertJsonPath('data.is_suspended', true);

        $this->patchJson("/api/v1/admin/salons/{$salon->id}/deactivate", [], $headers)
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_super_admin_can_manage_plans(): void
    {
        $headers = $this->authHeaders($this->superAdminToken());

        $create = $this->postJson('/api/v1/admin/plans', [
            'name' => 'Starter',
            'code' => 'starter',
            'price_fcfa' => 5000,
            'max_employees' => 5,
            'max_services' => 15,
            'has_online_booking' => true,
            'has_analytics' => false,
            'has_multi_branch' => false,
        ], $headers);

        $create->assertCreated()
            ->assertJsonPath('data.code', 'STARTER');

        $planId = $create->json('data.id');

        $this->patchJson("/api/v1/admin/plans/{$planId}/archive", [], $headers)
            ->assertOk()
            ->assertJsonPath('data.is_archived', true);
    }

    public function test_super_admin_can_list_billing_payments(): void
    {
        $plan = Plan::factory()->free()->create(['price_fcfa' => 25000]);
        $salon = Salon::factory()->create();
        $subscription = Subscription::query()->create([
            'salon_id' => $salon->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'is_trial' => false,
        ]);

        SubscriptionPayment::query()->create([
            'salon_id' => $salon->id,
            'subscription_id' => $subscription->id,
            'plan_id' => $plan->id,
            'amount' => 25000,
            'method' => BillingPaymentMethod::OrangeMoney,
            'status' => BillingPaymentStatus::Paid,
            'reference' => 'OM-123',
            'paid_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/admin/billing-payments', $this->authHeaders($this->superAdminToken()));

        $response->assertOk()
            ->assertJsonPath('data.0.amount', 25000)
            ->assertJsonPath('data.0.method', BillingPaymentMethod::OrangeMoney->value);
    }
}
