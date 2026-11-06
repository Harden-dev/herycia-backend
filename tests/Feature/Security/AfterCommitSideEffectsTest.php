<?php

namespace Tests\Feature\Security;

use App\Enums\SalonStaffRole;
use App\Enums\SubscriptionStatus;
use App\Events\SalonRegistered;
use App\Jobs\Storage\DeleteSalonLogoJob;
use App\Models\Plan;
use App\Models\Salon;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\PaymentAlertNotification;
use App\Services\Sms\TwilioSmsService;
use App\Services\Storage\SalonLogoStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Exigence « tout effet de bord faillible passe en file après commit » : un SMS, un mail ou une
 * suppression de fichier n'est jamais envoyé pour une transaction annulée, et son échec ne peut
 * pas annuler une transaction déjà validée.
 */
class AfterCommitSideEffectsTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_queue_connections_dispatch_after_commit(): void
    {
        foreach (['sync', 'database', 'beanstalkd', 'sqs', 'redis'] as $connection) {
            $this->assertTrue(config("queue.connections.{$connection}.after_commit"), $connection);
        }
    }

    public function test_queued_sms_listener_is_not_sent_when_transaction_rolls_back(): void
    {
        [$salon, $user, $subscription] = $this->registeredSalon();
        $this->mock(TwilioSmsService::class, fn ($mock) => $mock->shouldNotReceive('send'));

        $this->assertThrows(fn () => DB::transaction(function () use ($salon, $user, $subscription): void {
            event(new SalonRegistered($salon, $user, $subscription));

            throw new \RuntimeException('Échec métier après l\'événement');
        }), \RuntimeException::class, 'Échec métier après l\'événement');
    }

    public function test_sms_failure_after_commit_does_not_roll_back_transaction(): void
    {
        [$salon, $user, $subscription] = $this->registeredSalon();
        $this->mock(TwilioSmsService::class, fn ($mock) => $mock->shouldReceive('send')->once()->andThrow(new \RuntimeException('Twilio indisponible')));

        $this->assertThrows(fn () => DB::transaction(function () use ($salon, $user, $subscription): void {
            $salon->update(['name' => 'Salon validé']);
            event(new SalonRegistered($salon, $user, $subscription));
        }), \RuntimeException::class, 'Twilio indisponible');

        $this->assertDatabaseHas('salons', ['id' => $salon->id, 'name' => 'Salon validé']);
    }

    public function test_payment_alert_notification_is_queued_after_commit(): void
    {
        $notification = new PaymentAlertNotification('refunded_after_success', 'SAL-AFTERCOMMIT00001');

        $this->assertTrue($notification->afterCommit);
        $this->assertSame(config('geniuspay.queue'), $notification->queue);
    }

    public function test_previous_logo_is_deleted_by_queued_job_after_update(): void
    {
        Storage::fake('public');
        Queue::fake();
        $salon = Salon::factory()->create();
        Subscription::query()->create([
            'salon_id' => $salon->id,
            'plan_id' => Plan::factory()->free()->create()->id,
            'status' => SubscriptionStatus::Trial,
            'is_trial' => true,
        ]);
        $token = JWTAuth::fromUser(User::factory()->create(['salon_id' => $salon->id, 'role' => SalonStaffRole::Admin]));

        $this->uploadLogo($token)->assertOk();
        $previousPath = $salon->fresh()->logo_url;
        Queue::assertNothingPushed();

        $this->uploadLogo($token)->assertOk();

        $salon->refresh();
        $this->assertNotSame($previousPath, $salon->logo_url);
        Storage::disk('public')->assertExists([$previousPath, $salon->logo_url]);
        Queue::assertPushed(
            DeleteSalonLogoJob::class,
            fn (DeleteSalonLogoJob $job) => $job->path === $previousPath && $job->afterCommit === true,
        );

        Queue::pushed(DeleteSalonLogoJob::class)->first()->handle(app(SalonLogoStorageService::class));
        Storage::disk('public')->assertMissing($previousPath);
    }

    /**
     * @return array{0: Salon, 1: User, 2: Subscription}
     */
    private function registeredSalon(): array
    {
        $salon = Salon::factory()->create(['slug' => 'salon-after-commit']);
        $user = User::factory()->create(['salon_id' => $salon->id, 'phone' => '2250708112233']);
        $subscription = Subscription::query()->create([
            'salon_id' => $salon->id,
            'plan_id' => Plan::factory()->free()->create()->id,
            'status' => SubscriptionStatus::Trial,
            'is_trial' => true,
        ]);

        return [$salon, $user, $subscription];
    }

    private function uploadLogo(string $token): TestResponse
    {
        return $this->postJson('/api/v1/salon/logo', [
            'logo' => UploadedFile::fake()->image('logo.jpg'),
        ], ['Authorization' => 'Bearer '.$token]);
    }
}
