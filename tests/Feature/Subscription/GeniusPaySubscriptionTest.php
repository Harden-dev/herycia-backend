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
use App\Notifications\PaymentAlertNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class GeniusPaySubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://pay.genius.ci/api/v1/merchant';

    private const PRO_PRICE = 5250;

    private const ALERT_EMAIL = 'alertes@salono.test';

    private const GENERIC_PAYMENT_ERROR = 'Service de paiement momentanément indisponible.';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'geniuspay.public_key' => 'pk_sandbox_fake',
            'geniuspay.secret_key' => 'sk_sandbox_fake',
            'geniuspay.base_url' => self::API,
            'geniuspay.alert_email' => self::ALERT_EMAIL,
            'app.url' => 'http://localhost:8000',
            'salono.frontend_url' => 'http://localhost:5173',
        ]);
    }

    public function test_admin_can_initialize_geniuspay_payment(): void
    {
        $expiresAt = now()->addDay()->startOfSecond();
        $this->fakeCreatePayment(['expires_at' => $expiresAt->toIso8601String()]);
        [$salon, $pro, $token] = $this->trialSalonWithAdmin();

        $response = $this->initialize($token, 'b6f1c2d3-4e5f-4a6b-8c7d-9e0f1a2b3c4d');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.authorization_url', 'https://pay.genius.ci/checkout/MTX-A1B2C3D4E5')
            ->assertJsonPath('data.checkout_url', 'https://pay.genius.ci/checkout/MTX-A1B2C3D4E5')
            ->assertJsonPath('data.gateway_reference', 'MTX-A1B2C3D4E5')
            ->assertJsonPath('data.plan.code', 'pro')
            ->assertJsonPath('data.amount', self::PRO_PRICE)
            ->assertJsonPath('data.expires_at', $expiresAt->toIso8601String())
            ->assertJsonPath('data.idempotent_replay', false);

        $reference = $response->json('data.reference');
        $this->assertMatchesRegularExpression('/^SAL-[A-Z0-9]{16}$/', $reference);

        $this->assertDatabaseHas('payment_transactions', [
            'salon_id' => $salon->id,
            'plan_id' => $pro->id,
            'amount' => self::PRO_PRICE,
            'currency' => 'XOF',
            'reference' => $reference,
            'gateway_ref' => 'MTX-A1B2C3D4E5',
            'gateway' => 'geniuspay',
            'status' => PaymentTransactionStatus::Pending->value,
        ]);

        // Montant en unités entières (pas de ×100), clés en en-têtes, retour portant notre référence.
        Http::assertSent(function (Request $request) use ($reference) {
            return $request->url() === self::API.'/payments'
                && $request->hasHeader('X-API-Key', 'pk_sandbox_fake')
                && $request->hasHeader('X-API-Secret', 'sk_sandbox_fake')
                && $request['amount'] === self::PRO_PRICE
                && $request['currency'] === 'XOF'
                && $request['success_url'] === 'http://localhost:8000/api/v1/payment/callback?reference='.$reference
                && $request['metadata']['reference'] === $reference
                && ! isset($request['payment_method']);
        });
    }

    public function test_initialize_applies_default_ttl_when_expires_at_is_missing(): void
    {
        $this->freezeSecond();
        config(['geniuspay.checkout_ttl_minutes' => 30]);
        $this->fakeCreatePayment(['expires_at' => null]);
        [, , $token] = $this->trialSalonWithAdmin();

        $this->initialize($token, 'd1e2f3a4-b5c6-4d7e-8f9a-0b1c2d3e4f5a')
            ->assertOk()
            ->assertJsonPath('data.expires_at', now()->addMinutes(30)->toIso8601String());

        $this->assertTrue(PaymentTransaction::query()->sole()->checkout_expires_at->equalTo(now()->addMinutes(30)));
    }

    public function test_initialize_returns_generic_bad_gateway_when_geniuspay_fails(): void
    {
        Http::fake([
            self::API.'/payments' => Http::response([
                'success' => false,
                'error' => ['code' => 'PAYMENT_INIT_FAILED', 'message' => 'Init failed: merchant blocked'],
            ], 400),
        ]);
        [, , $token] = $this->trialSalonWithAdmin();

        $this->initialize($token, 'c7a2d3e4-5f6a-4b7c-9d8e-0f1a2b3c4d5e')
            ->assertStatus(502)
            ->assertJsonPath('message', self::GENERIC_PAYMENT_ERROR);

        // La ligne est conservée en échec (sans référence prestataire) pour l'audit.
        $this->assertDatabaseHas('payment_transactions', [
            'status' => PaymentTransactionStatus::Failed->value,
            'gateway_ref' => null,
        ]);
    }

    public function test_initialize_hides_missing_key_from_client(): void
    {
        config(['app.debug' => true, 'geniuspay.secret_key' => null]);
        Http::fake();
        [, , $token] = $this->trialSalonWithAdmin();

        $this->initialize($token, 'e2f3a4b5-c6d7-4e8f-9a0b-1c2d3e4f5a6b')
            ->assertStatus(502)
            ->assertJsonPath('message', self::GENERIC_PAYMENT_ERROR);

        Http::assertNothingSent();
    }

    public function test_sandbox_keys_are_refused_in_production(): void
    {
        $this->app['env'] = 'production';
        Http::fake();
        [, , $token] = $this->trialSalonWithAdmin();

        $this->initialize($token, 'f3a4b5c6-d7e8-4f9a-0b1c-2d3e4f5a6b7c')
            ->assertStatus(502)
            ->assertJsonPath('message', self::GENERIC_PAYMENT_ERROR);

        Http::assertNothingSent();
    }

    public function test_callback_verifies_and_activates_subscription(): void
    {
        [$salon, $pro, $subscription] = $this->trialSalonWithPendingPayment('SAL-CALLBACKTESTREF1', 'MTX-CALLBACK0001');
        $this->fakeVerify('MTX-CALLBACK0001', ['payment_method' => 'wave', 'payment_provider' => 'wave']);

        $this->get('/api/v1/payment/callback?reference=SAL-CALLBACKTESTREF1')
            ->assertRedirect('http://localhost:5173/settings?payment=success&reference=SAL-CALLBACKTESTREF1');

        $this->assertDatabaseHas('payment_transactions', [
            'reference' => 'SAL-CALLBACKTESTREF1',
            'status' => PaymentTransactionStatus::Success->value,
            'gateway_amount' => self::PRO_PRICE,
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
            'amount' => self::PRO_PRICE,
            'reference' => 'SAL-CALLBACKTESTREF1',
            'method' => 'wave',
            'status' => 'paid',
        ]);
    }

    public function test_amount_net_of_fees_within_tolerance_is_credited(): void
    {
        Notification::fake();
        $this->trialSalonWithPendingPayment('SAL-TOLERANCEREF0001', 'MTX-TOLERANCE01');
        // Brut 5018 (dans la tolérance de 8 %), net cohérent : 5018 - 232 = 4786 ; date illisible tolérée.
        $this->fakeVerify('MTX-TOLERANCE01', [
            'amount' => 5018,
            'fees' => 232,
            'net_amount' => 4786,
            'completed_at' => 'pas-une-date',
        ]);

        $this->get('/api/v1/payment/callback?reference=SAL-TOLERANCEREF0001')
            ->assertRedirectContains('payment=success');

        $this->assertDatabaseHas('payment_transactions', [
            'reference' => 'SAL-TOLERANCEREF0001',
            'status' => PaymentTransactionStatus::Success->value,
            'gateway_amount' => 5018,
            'gateway_fees' => 232,
            'gateway_net_amount' => 4786,
        ]);
        Notification::assertNothingSent();
    }

    public function test_amount_beyond_tolerance_needs_review_and_alerts(): void
    {
        Notification::fake();
        $this->trialSalonWithPendingPayment('SAL-AMOUNTMISMATCH01', 'MTX-AMOUNT00001');
        $this->fakeVerify('MTX-AMOUNT00001', ['amount' => 4000]);

        $this->get('/api/v1/payment/callback?reference=SAL-AMOUNTMISMATCH01')
            ->assertRedirectContains('payment=pending');

        $this->assertDatabaseHas('payment_transactions', [
            'reference' => 'SAL-AMOUNTMISMATCH01',
            'status' => PaymentTransactionStatus::NeedsReview->value,
            'review_reason' => 'amount_mismatch',
            'gateway_amount' => 4000,
        ]);
        $this->assertDatabaseCount('subscription_payments', 0);
        $this->assertAlertSent('amount_mismatch');
    }

    public function test_currency_mismatch_needs_review(): void
    {
        Notification::fake();
        $this->trialSalonWithPendingPayment('SAL-CURRENCYREF00001', 'MTX-CURRENCY001');
        $this->fakeVerify('MTX-CURRENCY001', ['currency' => 'EUR']);

        $this->get('/api/v1/payment/callback?reference=SAL-CURRENCYREF00001');

        $this->assertDatabaseHas('payment_transactions', [
            'reference' => 'SAL-CURRENCYREF00001',
            'status' => PaymentTransactionStatus::NeedsReview->value,
            'review_reason' => 'currency_mismatch',
        ]);
        $this->assertDatabaseCount('subscription_payments', 0);
        $this->assertAlertSent('currency_mismatch');
    }

    public function test_sandbox_payment_in_production_needs_review(): void
    {
        Notification::fake();
        $this->app['env'] = 'production';
        config(['geniuspay.public_key' => 'pk_live_fake', 'geniuspay.secret_key' => 'sk_live_fake']);
        $this->trialSalonWithPendingPayment('SAL-SANDBOXENVREF001', 'MTX-SANDBOX0001');
        $this->fakeVerify('MTX-SANDBOX0001', ['environment' => 'sandbox']);

        $this->get('/api/v1/payment/callback?reference=SAL-SANDBOXENVREF001');

        $this->assertDatabaseHas('payment_transactions', [
            'reference' => 'SAL-SANDBOXENVREF001',
            'status' => PaymentTransactionStatus::NeedsReview->value,
            'review_reason' => 'sandbox_environment',
        ]);
        $this->assertAlertSent('sandbox_environment');
    }

    public function test_activation_failure_after_payment_needs_review(): void
    {
        Notification::fake();
        [, , $subscription] = $this->trialSalonWithPendingPayment('SAL-NOSUBSCRIPTION01', 'MTX-NOSUB000001');
        $subscription->delete();
        $this->fakeVerify('MTX-NOSUB000001');

        $this->get('/api/v1/payment/callback?reference=SAL-NOSUBSCRIPTION01')
            ->assertRedirectContains('payment=pending');

        $this->assertDatabaseHas('payment_transactions', [
            'reference' => 'SAL-NOSUBSCRIPTION01',
            'status' => PaymentTransactionStatus::NeedsReview->value,
            'review_reason' => 'activation_failed',
        ]);
        $this->assertAlertSent('activation_failed');
    }

    public function test_callback_marks_cancelled_payment_as_failed(): void
    {
        $this->trialSalonWithPendingPayment('SAL-CANCELLEDREF0001', 'MTX-CANCEL00001');
        $this->fakeVerify('MTX-CANCEL00001', ['status' => 'cancelled']);

        $this->get('/api/v1/payment/callback?reference=SAL-CANCELLEDREF0001')
            ->assertRedirectContains('payment=failed');

        $this->assertDatabaseHas('payment_transactions', [
            'reference' => 'SAL-CANCELLEDREF0001',
            'status' => PaymentTransactionStatus::Failed->value,
        ]);
    }

    public function test_failure_cannot_overwrite_a_concurrent_success(): void
    {
        $this->trialSalonWithPendingPayment('SAL-RACEFAILURE00001', 'MTX-RACE0000001');

        // Pendant la relecture, un autre processus (webhook) confirme le paiement.
        Http::fake(function () {
            PaymentTransaction::query()->where('reference', 'SAL-RACEFAILURE00001')
                ->update(['status' => PaymentTransactionStatus::Success->value]);

            return Http::response($this->verifyBody('MTX-RACE0000001', ['status' => 'failed']));
        });

        $this->get('/api/v1/payment/callback?reference=SAL-RACEFAILURE00001');

        $this->assertDatabaseHas('payment_transactions', [
            'reference' => 'SAL-RACEFAILURE00001',
            'status' => PaymentTransactionStatus::Success->value,
        ]);
    }

    public function test_incomplete_api_response_keeps_transaction_pending(): void
    {
        $this->trialSalonWithPendingPayment('SAL-INCOMPLETEREF001', 'MTX-INCOMPLETE1');
        Http::fake([
            self::API.'/payments/MTX-INCOMPLETE1' => Http::response([
                'success' => true,
                'data' => ['reference' => 'MTX-INCOMPLETE1', 'status' => 'completed', 'currency' => 'XOF'],
            ]),
        ]);

        $this->get('/api/v1/payment/callback?reference=SAL-INCOMPLETEREF001')
            ->assertRedirectContains('payment=pending');

        $this->assertDatabaseHas('payment_transactions', [
            'reference' => 'SAL-INCOMPLETEREF001',
            'status' => PaymentTransactionStatus::Pending->value,
        ]);
        $this->assertDatabaseCount('subscription_payments', 0);
    }

    public function test_callback_rejects_malformed_reference_without_lookup(): void
    {
        Http::fake();

        $this->get('/api/v1/payment/callback?reference='.urlencode("SAL-abc'; DROP TABLE"))
            ->assertRedirect('http://localhost:5173/settings?payment=failed');
        $this->get('/api/v1/payment/callback')
            ->assertRedirect('http://localhost:5173/settings?payment=failed');

        Http::assertNothingSent();
    }

    public function test_callback_never_redirects_relatively_without_frontend_url(): void
    {
        config(['salono.frontend_url' => '']);

        $response = $this->get('/api/v1/payment/callback');

        $response->assertOk();
        $this->assertNull($response->headers->get('Location'));
    }

    private function assertAlertSent(string $reason): void
    {
        Notification::assertSentOnDemand(
            PaymentAlertNotification::class,
            fn (PaymentAlertNotification $notification, array $channels, object $notifiable) => $notification->reason === $reason
                && $notifiable->routes['mail'] === self::ALERT_EMAIL,
        );
    }

    private function initialize(string $token, string $key): TestResponse
    {
        return $this->postJson('/api/v1/subscription/initialize', ['plan_code' => 'pro'], [
            'Authorization' => 'Bearer '.$token,
            'Idempotency-Key' => $key,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function fakeCreatePayment(array $overrides = []): void
    {
        Http::fake([
            self::API.'/payments' => Http::response([
                'success' => true,
                'data' => array_merge([
                    'id' => 123,
                    'reference' => 'MTX-A1B2C3D4E5',
                    'amount' => self::PRO_PRICE,
                    'currency' => 'XOF',
                    'status' => 'pending',
                    'checkout_url' => 'https://pay.genius.ci/checkout/MTX-A1B2C3D4E5',
                    'payment_url' => 'https://pay.genius.ci/checkout/MTX-A1B2C3D4E5',
                ], $overrides),
            ], 201),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function fakeVerify(string $gatewayRef, array $overrides = []): void
    {
        Http::fake([self::API.'/payments/'.$gatewayRef => Http::response($this->verifyBody($gatewayRef, $overrides))]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function verifyBody(string $gatewayRef, array $overrides = []): array
    {
        return [
            'success' => true,
            'data' => array_merge([
                'reference' => $gatewayRef,
                'amount' => self::PRO_PRICE,
                'currency' => 'XOF',
                'status' => 'completed',
                'completed_at' => now()->toIso8601String(),
            ], $overrides),
        ];
    }

    /**
     * @return array{0: Salon, 1: Plan, 2: string}
     */
    private function trialSalonWithAdmin(): array
    {
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
        $admin = User::factory()->create(['salon_id' => $salon->id, 'role' => SalonStaffRole::Admin]);

        return [$salon, $pro, JWTAuth::fromUser($admin)];
    }

    /**
     * @return array{0: Salon, 1: Plan, 2: Subscription}
     */
    private function trialSalonWithPendingPayment(string $reference, string $gatewayRef): array
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

        PaymentTransaction::query()->create([
            'salon_id' => $salon->id,
            'plan_id' => $pro->id,
            'amount' => self::PRO_PRICE,
            'currency' => 'XOF',
            'reference' => $reference,
            'gateway_ref' => $gatewayRef,
            'status' => PaymentTransactionStatus::Pending,
        ]);

        return [$salon, $pro, $subscription];
    }
}
