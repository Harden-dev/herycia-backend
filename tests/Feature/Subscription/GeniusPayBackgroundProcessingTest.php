<?php

namespace Tests\Feature\Subscription;

use App\Enums\PaymentTransactionStatus;
use App\Enums\SubscriptionStatus;
use App\Jobs\Payment\ConfirmGeniusPayPaymentJob;
use App\Jobs\Payment\ProcessGeniusPayWebhookJob;
use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\Salon;
use App\Models\Subscription;
use App\Notifications\PaymentAlertNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Traitements en arrière-plan GeniusPay : webhook mis en file, réconciliation, remboursement.
 */
class GeniusPayBackgroundProcessingTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://pay.genius.ci/api/v1/merchant';

    private const SECRET = 'whsec_sandbox_secret';

    private const PRO_PRICE = 5250;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'geniuspay.public_key' => 'pk_sandbox_fake',
            'geniuspay.secret_key' => 'sk_sandbox_fake',
            'geniuspay.webhook_secret' => self::SECRET,
            'geniuspay.base_url' => self::API,
            'geniuspay.alert_email' => 'alertes@salono.test',
        ]);
    }

    public function test_webhook_dispatches_job_on_payments_queue_and_answers_immediately(): void
    {
        Queue::fake();
        Http::fake();

        $this->sendWebhook([
            'id' => 'evt_0001',
            'event' => 'payment.success',
            'data' => ['reference' => 'MTX-QUEUED00001', 'metadata' => ['reference' => 'SAL-QUEUEDREF0000100']],
        ])->assertOk();

        Queue::assertPushedOn('payments', ProcessGeniusPayWebhookJob::class, fn (ProcessGeniusPayWebhookJob $job) => $job->event === 'payment.success'
            && $job->gatewayReference === 'MTX-QUEUED00001'
            && $job->metadataReference === 'SAL-QUEUEDREF0000100');
        Http::assertNothingSent();
    }

    public function test_webhook_replay_is_deduplicated(): void
    {
        Queue::fake();
        $payload = ['id' => 'evt_replay', 'event' => 'payment.success', 'data' => ['reference' => 'MTX-REPLAY00001']];

        $this->sendWebhook($payload)->assertOk();
        $this->sendWebhook($payload)->assertOk();

        Queue::assertPushed(ProcessGeniusPayWebhookJob::class, 1);
    }

    public function test_webhook_without_event_id_is_deduplicated_on_signature(): void
    {
        Queue::fake();
        $payload = ['event' => 'payment.success', 'data' => ['reference' => 'MTX-NOID0000001']];
        $timestamp = (string) time();

        $this->sendWebhook($payload, $timestamp)->assertOk();
        $this->sendWebhook($payload, $timestamp)->assertOk();

        Queue::assertPushed(ProcessGeniusPayWebhookJob::class, 1);
    }

    public function test_webhook_is_refused_when_secret_is_missing(): void
    {
        Queue::fake();
        config(['geniuspay.webhook_secret' => null]);

        $this->sendWebhook(['event' => 'payment.success', 'data' => []])->assertStatus(503);

        Queue::assertNothingPushed();
    }

    public function test_webhook_sandbox_secret_is_refused_in_production(): void
    {
        Queue::fake();
        $this->app['env'] = 'production';

        $this->sendWebhook(['event' => 'payment.success', 'data' => []])->assertStatus(503);

        Queue::assertNothingPushed();
    }

    public function test_webhook_route_is_throttled(): void
    {
        Queue::fake();

        for ($i = 0; $i < 120; $i++) {
            $this->postJson('/api/v1/payment/webhook', [])->assertUnauthorized();
        }

        $this->postJson('/api/v1/payment/webhook', [])->assertStatus(429);
    }

    public function test_webhook_job_ignores_paystack_transactions(): void
    {
        Http::fake();
        $transaction = $this->pendingTransaction('SAL-PAYSTACKREF00010', 'MTX-PAYSTACK001');
        $transaction->forceFill(['gateway' => 'paystack'])->save();

        ProcessGeniusPayWebhookJob::dispatchSync('payment.success', 'MTX-PAYSTACK001', null);

        Http::assertNothingSent();
        $this->assertSame(PaymentTransactionStatus::Pending, $transaction->fresh()->status);
    }

    public function test_refund_after_success_revokes_period_and_alerts(): void
    {
        Notification::fake();
        $transaction = $this->pendingTransaction('SAL-REFUNDREF0000100', 'MTX-REFUND00001');
        Http::fake([
            self::API.'/payments/MTX-REFUND00001' => Http::sequence()
                ->push($this->verifyBody('MTX-REFUND00001', 'completed'))
                ->push($this->verifyBody('MTX-REFUND00001', 'refunded')),
        ]);

        ProcessGeniusPayWebhookJob::dispatchSync('payment.success', 'MTX-REFUND00001', null);
        $this->assertSame(PaymentTransactionStatus::Success, $transaction->fresh()->status);

        ProcessGeniusPayWebhookJob::dispatchSync('payment.refunded', 'MTX-REFUND00001', null);

        $this->assertSame(PaymentTransactionStatus::Refunded, $transaction->fresh()->status);
        $this->assertDatabaseHas('subscription_payments', ['reference' => 'SAL-REFUNDREF0000100', 'status' => 'refunded']);
        $this->assertDatabaseHas('subscriptions', [
            'salon_id' => $transaction->salon_id,
            'status' => SubscriptionStatus::Expired->value,
        ]);
        Notification::assertSentOnDemand(
            PaymentAlertNotification::class,
            fn (PaymentAlertNotification $notification) => $notification->reason === 'refunded_after_success',
        );
    }

    public function test_reconcile_dispatches_pending_and_recent_failed_ordered_by_last_check(): void
    {
        Queue::fake();
        $checkedRecently = $this->pendingTransaction('SAL-CHECKEDREF000100', 'MTX-CHECKED0001');
        $checkedRecently->forceFill(['created_at' => now()->subHours(2), 'last_checked_at' => now()->subMinutes(15)])->save();
        $failedNeverChecked = $this->pendingTransaction('SAL-FAILEDREF0000100', 'MTX-FAILED00001');
        $failedNeverChecked->forceFill(['status' => PaymentTransactionStatus::Failed, 'created_at' => now()->subHour()])->save();
        $tooOld = $this->pendingTransaction('SAL-TOOOLDREF0000100', 'MTX-TOOOLD00001');
        $tooOld->forceFill(['status' => PaymentTransactionStatus::Failed, 'created_at' => now()->subHours(50)])->save();
        $paystack = $this->pendingTransaction('SAL-PAYSTACKREF00020', 'MTX-PAYSTACK002');
        $paystack->forceFill(['gateway' => 'paystack', 'created_at' => now()->subHour()])->save();

        $this->artisan('geniuspay:reconcile')->assertSuccessful();

        $pushed = Queue::pushed(ConfirmGeniusPayPaymentJob::class)->map(fn (ConfirmGeniusPayPaymentJob $job) => $job->transactionId)->values()->all();
        // Jamais vérifiée d'abord, puis la plus anciennement vérifiée ; ni trop ancienne ni Paystack.
        $this->assertSame([$failedNeverChecked->id, $checkedRecently->id], $pushed);
        Queue::assertPushedOn('payments', ConfirmGeniusPayPaymentJob::class);
        $this->assertTrue($failedNeverChecked->fresh()->last_checked_at->isToday());
    }

    public function test_reconcile_recovers_late_confirmation_of_failed_payment(): void
    {
        $transaction = $this->pendingTransaction('SAL-LATECONFIRM00010', 'MTX-LATE0000001');
        $transaction->forceFill(['status' => PaymentTransactionStatus::Failed, 'created_at' => now()->subHour()])->save();
        $this->fakeVerify('MTX-LATE0000001', 'completed');

        $this->artisan('geniuspay:reconcile')->assertSuccessful();

        $this->assertSame(PaymentTransactionStatus::Success, $transaction->fresh()->status);
        $this->assertDatabaseHas('subscriptions', [
            'salon_id' => $transaction->salon_id,
            'status' => SubscriptionStatus::Active->value,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sendWebhook(array $payload, ?string $timestamp = null): TestResponse
    {
        $body = (string) json_encode($payload);
        $timestamp ??= (string) time();

        return $this->call('POST', '/api/v1/payment/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => hash_hmac('sha256', $timestamp.'.'.$body, self::SECRET),
            'HTTP_X_WEBHOOK_TIMESTAMP' => $timestamp,
        ], $body);
    }

    private function fakeVerify(string $gatewayRef, string $status): void
    {
        Http::fake([self::API.'/payments/'.$gatewayRef => Http::response($this->verifyBody($gatewayRef, $status))]);
    }

    /**
     * @return array<string, mixed>
     */
    private function verifyBody(string $gatewayRef, string $status): array
    {
        return [
            'success' => true,
            'data' => [
                'reference' => $gatewayRef,
                'amount' => self::PRO_PRICE,
                'currency' => 'XOF',
                'status' => $status,
                'payment_method' => 'wave',
                'completed_at' => now()->toIso8601String(),
            ],
        ];
    }

    private function pendingTransaction(string $reference, string $gatewayRef): PaymentTransaction
    {
        $salon = Salon::factory()->create();
        $basic = Plan::query()->where('code', 'basic')->first() ?? Plan::factory()->basic()->create();
        $pro = Plan::query()->where('code', 'pro')->first() ?? Plan::factory()->pro()->create();
        Subscription::query()->create([
            'salon_id' => $salon->id,
            'plan_id' => $basic->id,
            'status' => SubscriptionStatus::Trial,
            'is_trial' => true,
        ]);

        return PaymentTransaction::query()->create([
            'salon_id' => $salon->id,
            'plan_id' => $pro->id,
            'amount' => self::PRO_PRICE,
            'currency' => 'XOF',
            'reference' => $reference,
            'gateway_ref' => $gatewayRef,
            'status' => PaymentTransactionStatus::Pending,
        ]);
    }
}
