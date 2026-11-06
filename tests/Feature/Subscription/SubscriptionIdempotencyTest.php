<?php

namespace Tests\Feature\Subscription;

use App\Enums\PaymentTransactionStatus;
use App\Enums\SalonStaffRole;
use App\Enums\SubscriptionStatus;
use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\Salon;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class SubscriptionIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://pay.genius.ci/api/v1/merchant';

    private const ENDPOINT = '/api/v1/subscription/initialize';

    private const KEY = '9b2f6a1e-3c4d-4e5f-8a9b-0c1d2e3f4a5b';

    private const CHECKOUT_URL = 'https://pay.genius.ci/checkout/MTX-IDEMPOTENT01';

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'geniuspay.public_key' => 'pk_sandbox_fake',
            'geniuspay.secret_key' => 'sk_sandbox_fake',
            'geniuspay.base_url' => self::API,
            'app.url' => 'http://localhost:8000',
        ]);

        $salon = Salon::factory()->create();
        $basic = Plan::factory()->basic()->create();
        Plan::factory()->pro()->create();
        Subscription::query()->create([
            'salon_id' => $salon->id,
            'plan_id' => $basic->id,
            'status' => SubscriptionStatus::Trial,
            'is_trial' => true,
            'trial_ends_at' => now()->addDays(3),
        ]);
        $admin = User::factory()->create(['salon_id' => $salon->id, 'role' => SalonStaffRole::Admin]);
        $this->token = JWTAuth::fromUser($admin);
    }

    public function test_missing_idempotency_key_is_rejected(): void
    {
        Http::fake();

        $this->postJson(self::ENDPOINT, ['plan_code' => 'pro'], ['Authorization' => 'Bearer '.$this->token])
            ->assertStatus(422)
            ->assertJsonValidationErrors('idempotency_key');

        Http::assertNothingSent();
    }

    public function test_invalid_idempotency_key_is_rejected(): void
    {
        Http::fake();

        $this->initialize('pro', 'short')->assertStatus(422)->assertJsonValidationErrors('idempotency_key');
        $this->initialize('pro', 'invalid key with spaces!')->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_same_key_replays_response_without_calling_geniuspay_again(): void
    {
        $this->fakeGeniusPaySuccess();

        $first = $this->initialize('pro')
            ->assertOk()
            ->assertJsonPath('data.idempotent_replay', false)
            ->assertHeader('Idempotent-Replayed', 'false');

        $second = $this->initialize('pro')
            ->assertOk()
            ->assertJsonPath('data.idempotent_replay', true)
            ->assertHeader('Idempotent-Replayed', 'true')
            ->assertJsonPath('data.checkout_url', self::CHECKOUT_URL)
            ->assertJsonPath('data.authorization_url', self::CHECKOUT_URL)
            ->assertJsonPath('data.gateway_reference', 'MTX-IDEMPOTENT01')
            ->assertJsonPath('data.plan.code', 'pro');

        $this->assertSame($first->json('data.reference'), $second->json('data.reference'));
        Http::assertSentCount(1);
        $this->assertDatabaseCount('payment_transactions', 1);
        $this->assertDatabaseHas('payment_transactions', [
            'idempotency_key' => self::KEY,
            'checkout_url' => self::CHECKOUT_URL,
        ]);
    }

    public function test_same_key_with_another_plan_is_rejected(): void
    {
        $this->fakeGeniusPaySuccess();

        $this->initialize('pro')->assertOk();
        $this->initialize('basic')->assertStatus(422)->assertJsonPath('success', false);

        Http::assertSentCount(1);
        $this->assertDatabaseCount('payment_transactions', 1);
    }

    public function test_same_key_after_successful_payment_returns_conflict(): void
    {
        $this->fakeGeniusPaySuccess();
        $this->initialize('pro')->assertOk();
        PaymentTransaction::query()->update(['status' => PaymentTransactionStatus::Success]);

        $this->initialize('pro')
            ->assertStatus(409)
            ->assertJsonPath('code', 'payment_already_completed');

        Http::assertSentCount(1);
    }

    public function test_same_key_after_failed_payment_returns_conflict(): void
    {
        $this->fakeGeniusPaySuccess();
        $this->initialize('pro')->assertOk();
        PaymentTransaction::query()->update(['status' => PaymentTransactionStatus::Failed]);

        $this->initialize('pro')
            ->assertStatus(409)
            ->assertJsonPath('code', 'idempotency_key_expired');

        Http::assertSentCount(1);
    }

    public function test_same_key_after_checkout_expiry_returns_conflict(): void
    {
        $this->fakeGeniusPaySuccess();
        $this->initialize('pro')->assertOk();
        PaymentTransaction::query()->update(['checkout_expires_at' => now()->subMinute()]);

        $this->initialize('pro')
            ->assertStatus(409)
            ->assertJsonPath('code', 'idempotency_key_expired');

        Http::assertSentCount(1);
    }

    public function test_same_key_can_be_retried_after_geniuspay_failure(): void
    {
        Http::fake([
            self::API.'/payments' => Http::sequence()
                ->push(['success' => false, 'error' => ['code' => 'PAYMENT_INIT_FAILED', 'message' => 'Init failed']], 400)
                ->push($this->geniusPayPaymentBody(), 201),
        ]);

        $this->initialize('pro')->assertStatus(502);
        // L'échec est conservé (ligne Failed sans référence prestataire)…
        $this->assertDatabaseHas('payment_transactions', [
            'idempotency_key' => self::KEY,
            'status' => PaymentTransactionStatus::Failed->value,
            'gateway_ref' => null,
        ]);

        // …et la même clé réessaie sur la même ligne.
        $this->initialize('pro')
            ->assertOk()
            ->assertJsonPath('data.idempotent_replay', false)
            ->assertJsonPath('data.checkout_url', self::CHECKOUT_URL);

        Http::assertSentCount(2);
        $this->assertDatabaseCount('payment_transactions', 1);
        $this->assertDatabaseHas('payment_transactions', [
            'idempotency_key' => self::KEY,
            'status' => PaymentTransactionStatus::Pending->value,
            'gateway_ref' => 'MTX-IDEMPOTENT01',
        ]);
    }

    public function test_same_key_while_first_request_is_in_flight_returns_in_progress(): void
    {
        $this->fakeGeniusPaySuccess();
        $this->initialize('pro')->assertOk();
        // Ligne créée, lien pas encore enregistré (requête concurrente sans verrou partagé).
        PaymentTransaction::query()->update(['gateway_ref' => null, 'checkout_url' => null]);

        $this->initialize('pro')
            ->assertStatus(409)
            ->assertJsonPath('code', 'request_in_progress');

        Http::assertSentCount(1);
    }

    private function initialize(string $planCode, string $key = self::KEY): TestResponse
    {
        return $this->postJson(self::ENDPOINT, ['plan_code' => $planCode], [
            'Authorization' => 'Bearer '.$this->token,
            'Idempotency-Key' => $key,
        ]);
    }

    private function fakeGeniusPaySuccess(): void
    {
        Http::fake([self::API.'/payments' => Http::response($this->geniusPayPaymentBody(), 201)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function geniusPayPaymentBody(): array
    {
        return [
            'success' => true,
            'data' => [
                'id' => 456,
                'reference' => 'MTX-IDEMPOTENT01',
                'amount' => 5250,
                'currency' => 'XOF',
                'status' => 'pending',
                'checkout_url' => self::CHECKOUT_URL,
                'payment_url' => self::CHECKOUT_URL,
                'expires_at' => now()->addDay()->toIso8601String(),
            ],
        ];
    }
}
