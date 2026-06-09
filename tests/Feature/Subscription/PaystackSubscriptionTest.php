<?php

namespace Tests\Feature\Subscription;

use App\Enums\PaymentTransactionStatus;
use App\Enums\SalonStaffRole;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Salon;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class PaystackSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'paystack.secret_key' => 'sk_test_fake',
            'paystack.payment_url' => 'https://api.paystack.co',
            'paystack.merchant_email' => 'admin@salono.ci',
            'app.url' => 'http://localhost:8000',
            'salono.frontend_url' => 'http://localhost:5173',
        ]);
    }

    public function test_admin_can_initialize_paystack_payment(): void
    {
        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'message' => 'Authorization URL created',
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/abc123',
                    'access_code' => 'access_code_123',
                    'reference' => 'SAL-TESTREF123456',
                ],
            ], 200),
        ]);

        $salon = Salon::factory()->create();
        $basic = Plan::factory()->basic()->create();
        $pro = Plan::factory()->pro()->create();
        Subscription::query()->create([
            'salon_id' => $salon->id,
            'plan_id' => $basic->id,
            'status' => SubscriptionStatus::Trial,
            'is_trial' => true,
            'trial_ends_at' => now()->addDays(3),
        ]);
        $admin = User::factory()->create([
            'salon_id' => $salon->id,
            'role' => SalonStaffRole::Admin,
            'email' => 'admin@salon.test',
        ]);
        $token = JWTAuth::fromUser($admin);

        $response = $this->postJson('/api/v1/subscription/initialize', [
            'plan_code' => 'pro',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.authorization_url', 'https://checkout.paystack.com/abc123')
            ->assertJsonPath('data.plan.code', 'pro')
            ->assertJsonPath('data.amount', 15000);

        $this->assertDatabaseHas('payment_transactions', [
            'salon_id' => $salon->id,
            'plan_id' => $pro->id,
            'amount' => 15000,
            'currency' => 'XOF',
            'status' => PaymentTransactionStatus::Pending->value,
        ]);
    }

    public function test_callback_verifies_and_activates_subscription(): void
    {
        $salon = Salon::factory()->create();
        $basic = Plan::factory()->basic()->create();
        $pro = Plan::factory()->pro()->create();
        $subscription = Subscription::query()->create([
            'salon_id' => $salon->id,
            'plan_id' => $basic->id,
            'status' => SubscriptionStatus::Trial,
            'is_trial' => true,
        ]);

        $reference = 'SAL-CALLBACKTESTREF';
        \App\Models\PaymentTransaction::query()->create([
            'salon_id' => $salon->id,
            'plan_id' => $pro->id,
            'amount' => 15000,
            'currency' => 'XOF',
            'reference' => $reference,
            'status' => PaymentTransactionStatus::Pending,
        ]);

        Http::fake([
            'https://api.paystack.co/transaction/verify/'.$reference => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'reference' => $reference,
                    'amount' => 1_500_000,
                    'currency' => 'XOF',
                    'paid_at' => now()->toIso8601String(),
                    'channel' => 'card',
                ],
            ], 200),
        ]);

        $response = $this->get('/api/v1/payment/callback?reference='.$reference);

        $response->assertRedirect('http://localhost:5173/settings?payment=success&reference='.$reference);

        $this->assertDatabaseHas('payment_transactions', [
            'reference' => $reference,
            'status' => PaymentTransactionStatus::Success->value,
        ]);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'plan_id' => $pro->id,
            'status' => SubscriptionStatus::Active->value,
            'is_trial' => false,
        ]);

        $this->assertDatabaseHas('subscription_payments', [
            'salon_id' => $salon->id,
            'subscription_id' => $subscription->id,
            'plan_id' => $pro->id,
            'amount' => 15000,
            'reference' => $reference,
            'status' => 'paid',
        ]);
    }

    public function test_callback_redirects_failed_when_reference_missing(): void
    {
        $response = $this->get('/api/v1/payment/callback');

        $response->assertRedirect('http://localhost:5173/settings?payment=failed');
    }
}
