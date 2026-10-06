<?php

namespace Tests\Feature\Security;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\SalonStaffRole;
use App\Enums\SubscriptionStatus;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\Salon;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Auth\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesBookableSalon;
use Tests\Concerns\CreatesSalonSubscription;
use Tests\TestCase;

/**
 * Tests de non-régression des corrections de l'audit (docs/AUDIT_SECURITE_ARCHITECTURE.md).
 */
class SecurityHardeningTest extends TestCase
{
    use CreatesBookableSalon;
    use CreatesSalonSubscription;
    use RefreshDatabase;

    private function salonAdmin(?Salon $salon = null): User
    {
        $salon ??= Salon::factory()->create(['is_active' => true]);

        return User::factory()->create([
            'salon_id' => $salon->id,
            'role' => SalonStaffRole::Admin,
            'is_active' => true,
        ]);
    }

    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer '.app(JwtService::class)->createToken($user)];
    }

    /** C2 — les routes OTP qui permettaient de réactiver un compte bloqué n'existent plus. */
    public function test_otp_routes_are_removed(): void
    {
        $this->postJson('/api/v1/auth/resend-otp', ['login' => 'x@test.com'])->assertNotFound();
        $this->postJson('/api/v1/auth/verify-otp', ['login' => 'x@test.com', 'code' => '123456'])->assertNotFound();
    }

    /** H2 — un jeton émis avant le blocage du compte est refusé. */
    public function test_token_of_deactivated_user_is_rejected(): void
    {
        $user = $this->salonAdmin();
        $headers = $this->bearer($user);

        $user->update(['is_active' => false]);

        $this->getJson('/api/v1/auth/me', $headers)
            ->assertUnauthorized()
            ->assertJsonPath('error', 'account_disabled');
    }

    /** H2 — un changement de mot de passe révoque les jetons existants. */
    public function test_token_is_revoked_after_password_change(): void
    {
        $user = $this->salonAdmin();
        $headers = $this->bearer($user);

        $this->travel(2)->seconds();
        $user->update(['password' => 'NouveauMotDePasse1!']);

        $this->getJson('/api/v1/auth/me', $headers)
            ->assertUnauthorized()
            ->assertJsonPath('error', 'token_revoked');
    }

    /** H3 — un salon suspendu n'a plus accès au back-office ni à la connexion. */
    public function test_suspended_salon_is_blocked(): void
    {
        $salon = Salon::factory()->create(['is_active' => true]);
        $this->createActiveSubscription($salon);
        $user = User::factory()->create([
            'salon_id' => $salon->id,
            'phone' => '2250700000001',
            'password' => 'motdepasse123',
            'role' => SalonStaffRole::Admin,
            'is_active' => true,
        ]);
        $headers = $this->bearer($user);

        $salon->update(['suspended_at' => now()]);

        $this->getJson('/api/v1/clients', $headers)
            ->assertForbidden()
            ->assertJsonPath('error', 'salon_suspended');

        $this->postJson('/api/v1/auth/login', ['login' => '2250700000001', 'password' => 'motdepasse123'])
            ->assertUnauthorized();
    }

    /** M4 — la suppression d'un salon est logique : les données financières sont conservées. */
    public function test_salon_deletion_is_soft(): void
    {
        $salon = Salon::factory()->create();
        $salon->delete();

        $this->assertSoftDeleted('salons', ['id' => $salon->id]);
    }

    /** C3 — la simulation de paiement est désactivée par défaut. */
    public function test_payment_simulation_is_disabled_by_default(): void
    {
        $this->assertFalse((bool) config('salono.simulate_subscription_payments'));

        $user = $this->salonAdmin();

        $this->postJson('/api/v1/subscription/simulate-payment', ['method' => 'cash'], $this->bearer($user))
            ->assertStatus(400);
    }

    /** C5 — la connexion est limitée en débit par identifiant. */
    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['login' => '2250700000099', 'password' => 'mauvaismdp'])
                ->assertUnauthorized();
        }

        $this->postJson('/api/v1/auth/login', ['login' => '2250700000099', 'password' => 'mauvaismdp'])
            ->assertStatus(429)
            ->assertJsonPath('error', 'too_many_requests');
    }

    /** H9 — mot de passe oublié : même réponse que l'email existe ou non. */
    public function test_forgot_password_does_not_reveal_accounts(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'inconnu@test.com'])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    /** H7 — le suivi public n'expose pas les notes internes. */
    public function test_public_tracking_hides_internal_notes(): void
    {
        ['salon' => $salon, 'stylist' => $stylist, 'service' => $service] = $this->createBookableSalon();
        $client = Client::factory()->create(['salon_id' => $salon->id]);

        Appointment::withoutEvents(fn () => Appointment::query()->create([
            'salon_id' => $salon->id,
            'client_id' => $client->id,
            'user_id' => $stylist->id,
            'service_id' => $service->id,
            'scheduled_at' => now()->addDay(),
            'status' => AppointmentStatus::Confirmed,
            'tracking_token' => 'tok-notes-1',
            'notes' => 'Client difficile, impayé précédent',
        ]));

        $this->getJson('/api/rdv/tok-notes-1')
            ->assertOk()
            ->assertJsonMissingPath('data.notes');
    }

    /** H5 — la réservation publique ne renomme pas une fiche client existante. */
    public function test_public_booking_does_not_rename_existing_client(): void
    {
        ['salon' => $salon, 'stylist' => $stylist, 'service' => $service] = $this->createBookableSalon();
        $client = Client::factory()->create([
            'salon_id' => $salon->id,
            'name' => 'Aya Kouassi',
            'phone' => '2250707070707',
        ]);

        $scheduledAt = now()->addDay()->setTime(10, 0);

        $this->postJson('/api/booking/'.$salon->slug, [
            'client_phone' => '0707070707',
            'client_name' => 'Nom Usurpé',
            'service_id' => $service->id,
            'user_id' => $stylist->id,
            'date' => $scheduledAt->format('Y-m-d'),
            'time' => $scheduledAt->format('H:i'),
        ])->assertCreated();

        $this->assertSame('Aya Kouassi', $client->fresh()->name);
    }

    /** H5 — horizon maximal de réservation. */
    public function test_public_booking_rejects_far_future_dates(): void
    {
        ['salon' => $salon, 'stylist' => $stylist, 'service' => $service] = $this->createBookableSalon();
        $scheduledAt = now()->addDays(200)->setTime(10, 0);

        $this->postJson('/api/booking/'.$salon->slug, [
            'client_phone' => '0707070708',
            'client_name' => 'Client Lointain',
            'service_id' => $service->id,
            'user_id' => $stylist->id,
            'date' => $scheduledAt->format('Y-m-d'),
            'time' => $scheduledAt->format('H:i'),
        ])->assertStatus(422)->assertJsonPath('code', 'invalid_schedule');
    }

    /** H1 — l'extension du logo est déduite du contenu, pas du nom envoyé (ex. .html → XSS stocké). */
    public function test_logo_extension_ignores_client_filename(): void
    {
        Storage::fake('public');
        $salon = Salon::factory()->create(['is_active' => true]);
        $this->createActiveSubscription($salon);
        $user = $this->salonAdmin($salon);

        $file = UploadedFile::fake()->image('logo.png');
        $renamed = new UploadedFile($file->getPathname(), 'logo.html', 'image/png', null, true);

        $this->post('/api/v1/salon/logo', ['logo' => $renamed], $this->bearer($user) + ['Accept' => 'application/json'])
            ->assertOk();

        $files = Storage::disk('public')->allFiles('logos');
        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.png', $files[0]);
    }

    /** H4 — un webhook Paystack non signé est rejeté. */
    public function test_paystack_webhook_rejects_invalid_signature(): void
    {
        config(['paystack.secret_key' => 'sk_test_secret']);

        $this->postJson('/api/v1/payment/webhook', ['event' => 'charge.success'], ['x-paystack-signature' => 'faux'])
            ->assertUnauthorized();
    }

    /** H4 — webhook signé : active l'abonnement une seule fois, même rejoué. */
    public function test_paystack_webhook_activates_subscription_once(): void
    {
        config(['paystack.secret_key' => 'sk_test_secret']);

        $salon = Salon::factory()->create();
        $basic = Plan::factory()->basic()->create();
        $pro = Plan::factory()->pro()->create();
        Subscription::query()->create([
            'salon_id' => $salon->id,
            'plan_id' => $basic->id,
            'status' => SubscriptionStatus::Trial,
            'is_trial' => true,
        ]);

        $reference = 'SAL-WEBHOOKREF0001';
        PaymentTransaction::query()->create([
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

        $body = json_encode(['event' => 'charge.success', 'data' => ['reference' => $reference]]);
        $signature = hash_hmac('sha512', $body, 'sk_test_secret');

        for ($i = 0; $i < 2; $i++) {
            $this->call('POST', '/api/v1/payment/webhook', [], [], [], [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
            ], $body)->assertOk();
        }

        $this->assertDatabaseHas('payment_transactions', [
            'reference' => $reference,
            'status' => PaymentTransactionStatus::Success->value,
        ]);
        $this->assertSame(1, \App\Models\SubscriptionPayment::query()->where('reference', $reference)->count());
    }

    /** H4 — une erreur de vérification Paystack laisse la transaction en attente. */
    public function test_paystack_verification_error_keeps_transaction_pending(): void
    {
        config(['paystack.secret_key' => 'sk_test_secret']);

        $salon = Salon::factory()->create();
        $plan = Plan::factory()->pro()->create();
        Subscription::query()->create([
            'salon_id' => $salon->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Trial,
            'is_trial' => true,
        ]);

        $reference = 'SAL-PENDINGREF0001';
        PaymentTransaction::query()->create([
            'salon_id' => $salon->id,
            'plan_id' => $plan->id,
            'amount' => 15000,
            'currency' => 'XOF',
            'reference' => $reference,
            'status' => PaymentTransactionStatus::Pending,
        ]);

        Http::fake(['*' => Http::response(['status' => false, 'message' => 'Erreur'], 500)]);

        $this->get('/api/v1/payment/callback?reference='.$reference);

        $this->assertDatabaseHas('payment_transactions', [
            'reference' => $reference,
            'status' => PaymentTransactionStatus::Pending->value,
        ]);
    }
}
