<?php

namespace Tests\Feature\Security;

use App\Enums\SalonStaffRole;
use App\Enums\SubscriptionStatus;
use App\Events\SalonRegistered;
use App\Jobs\Sms\SendAppointmentReminderSms;
use App\Jobs\Sms\SendAppointmentTrackingSms;
use App\Jobs\Sms\SendQueueSoonSms;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Plan;
use App\Models\QueueEntry;
use App\Models\Salon;
use App\Models\Service;
use App\Models\Subscription;
use App\Models\User;
use App\Rules\SafeName;
use App\Services\Salon\SalonBookingQrCodeService;
use App\Services\Sms\SmsDeliveryGuard;
use App\Services\Sms\TwilioSmsService;
use App\Services\User\UserNotification;
use App\Support\IvoryCoastPhone;
use App\Support\Pii;
use App\Support\SmsText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesBookableSalon;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Non-régression des constats SMS du passage final (docs/PENTEST_PAIEMENT_GENIUSPAY.md, P1, P2, P5) :
 * chaque test rejoue le scénario d'abus décrit dans le rapport.
 */
class SmsAbuseRegressionTest extends TestCase
{
    use CreatesBookableSalon;
    use RefreshDatabase;

    private const PHISHING_ADMIN_NAME = "client.\nCompte Wave suspendu: https://wave-ci.evil.example/l\n\nWave";

    private const PHISHING_SERVICE_NAME = 'Votre colis est bloque https://evil.example/p';

    private const PHISHING_STAFF_NAME = 'Appelez le 0700000000';

    private const VICTIM = '2250707070707';

    /** @var list<string> */
    private array $sentBodies = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['salono.frontend_url' => 'https://salono.ci']);
    }

    // --- P1 : hameçonnage par SMS avec l'expéditeur de la plateforme --------------------------

    /** @return array<string, array{0: string, 1: list<string>}> */
    public static function phishingTextProvider(): array
    {
        return [
            'nom de gérant du rapport' => [self::PHISHING_ADMIN_NAME, ['http', 'evil', "\n", 'wave-ci']],
            'prestation avec lien' => [self::PHISHING_SERVICE_NAME, ['http', 'evil', '/p']],
            'coiffeur avec numéro' => [self::PHISHING_STAFF_NAME, ['0700000000']],
            'numéro espacé' => ['Rappelez 07 00 00 00 00', ['07 00', '00 00']],
            'domaine nu' => ['Payez sur wave-ci.com vite', ['wave-ci', '.com']],
            'domaine espacé' => ['Allez sur evil . example', ['evil', 'example']],
            'www sans schéma' => ['Voir www.evil.example', ['www', 'evil']],
            'emojis et bidi' => ["Promo 🎁\u{202E}lien", ['🎁', "\u{202E}"]],
        ];
    }

    /** @param  list<string>  $forbidden */
    #[DataProvider('phishingTextProvider')]
    public function test_p1_sms_sanitizer_neutralises_phishing_payloads(string $input, array $forbidden): void
    {
        $clean = SmsText::clean($input);

        foreach ($forbidden as $needle) {
            $this->assertStringNotContainsString($needle, $clean);
        }

        $this->assertLessThanOrEqual(SmsText::DEFAULT_MAX_LENGTH, mb_strlen($clean));
    }

    public function test_p1_sms_sanitizer_keeps_legitimate_names(): void
    {
        $this->assertSame("Kouadio N'Guessan", SmsText::clean("Kouadio N'Guessan"));
        $this->assertSame('St. Pierre Coiffure', SmsText::clean('St. Pierre Coiffure'));
        $this->assertSame('Tresses 3 rangs (longues)', SmsText::clean('Tresses 3 rangs (longues)'));
    }

    public function test_p1_registration_refuses_phishing_admin_and_salon_names(): void
    {
        Event::fake();
        $this->mock(SalonBookingQrCodeService::class)->shouldReceive('generateDataUri')->andReturn('data:image/png;base64,iVBORw0KGgo=');
        Plan::factory()->basic()->create();

        $this->postJson('/api/v1/auth/register', $this->registerPayload(['admin_name' => self::PHISHING_ADMIN_NAME]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['admin_name']);

        $this->postJson('/api/v1/auth/register', $this->registerPayload(['salon_name' => 'wave-ci.evil.example']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['salon_name']);

        $this->postJson('/api/v1/auth/register', $this->registerPayload(['salon_name' => "Salon\nAppelez 0700000000"]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['salon_name']);

        Event::assertNotDispatched(SalonRegistered::class);
    }

    public function test_p1_service_and_public_client_names_refuse_links_and_phone_numbers(): void
    {
        Queue::fake();
        ['salon' => $salon, 'stylist' => $stylist, 'service' => $service] = $this->createBookableSalon();
        $admin = User::factory()->create(['salon_id' => $salon->id, 'role' => SalonStaffRole::Admin]);
        $headers = ['Authorization' => 'Bearer '.JWTAuth::fromUser($admin)];

        $this->postJson('/api/v1/services', ['name' => self::PHISHING_SERVICE_NAME, 'duration_min' => 30, 'price' => 1000], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $this->postJson('/api/booking/'.$salon->slug, [
            'client_phone' => '2250708112233',
            'client_name' => 'Wave: www.evil.example',
            'service_id' => $service->id,
            'user_id' => $stylist->id,
            'scheduled_at' => now()->addDay()->setTime(10, 0)->toIso8601String(),
        ])->assertUnprocessable()->assertJsonValidationErrors(['client_name']);
    }

    public function test_p1_name_rules_accept_legitimate_names_and_refuse_injection(): void
    {
        $person = fn (string $name): bool => Validator::make(['name' => $name], ['name' => [SafeName::person()]])->passes();
        $label = fn (string $name): bool => Validator::make(['name' => $name], ['name' => [SafeName::label()]])->passes();

        $this->assertTrue($person("Marie-Ange N'Guessan"));
        $this->assertTrue($person('Dr. Koné'));
        $this->assertTrue($label('Coupe + barbe'));
        $this->assertTrue($label('Tresses 3 rangs (longues)'));

        $this->assertFalse($person(self::PHISHING_STAFF_NAME));
        $this->assertFalse($person("Koffi\nWave"));
        $this->assertFalse($person('koffi.ci'));
        $this->assertFalse($label('Salon 0707070707'));
        $this->assertFalse($label('Promo wave-ci . com'));
        $this->assertFalse($label('Soin 🎁'));
    }

    public function test_p1_appointment_sms_do_not_relay_injected_service_staff_and_salon_names(): void
    {
        Queue::fake();
        ['salon' => $salon, 'stylist' => $stylist, 'service' => $service] = $this->createBookableSalon();
        // Données déjà en base (antérieures à la validation) : le nettoyage à l'envoi suffit seul.
        Service::query()->whereKey($service->id)->update(['name' => self::PHISHING_SERVICE_NAME]);
        User::query()->whereKey($stylist->id)->update(['name' => self::PHISHING_STAFF_NAME]);
        Salon::query()->whereKey($salon->id)->update(['name' => "Salono\nCompte bloque: evil.example"]);
        $client = Client::factory()->create(['salon_id' => $salon->id, 'phone' => self::VICTIM, 'name' => self::PHISHING_ADMIN_NAME]);
        $appointment = Appointment::factory()->create([
            'salon_id' => $salon->id,
            'client_id' => $client->id,
            'user_id' => $stylist->id,
            'service_id' => $service->id,
            'scheduled_at' => now()->addHour(),
            'tracking_token' => 'Trk0000001',
        ]);
        $entry = QueueEntry::query()->create([
            'salon_id' => $salon->id,
            'client_id' => $client->id,
            'user_id' => $stylist->id,
            'service_id' => $service->id,
            'status' => 'waiting',
            'position' => 1,
            'arrived_at' => now(),
        ]);
        $this->captureSms();

        app()->call([new SendAppointmentTrackingSms($appointment), 'handle']);
        app()->call([new SendAppointmentReminderSms($appointment), 'handle']);
        app()->call([new SendQueueSoonSms($entry), 'handle']);

        $this->assertCount(3, $this->sentBodies);
        // Aucun saut de ligne injecté : le gabarit du SMS de confirmation compte 9 sauts de ligne.
        $this->assertSame(9, substr_count($this->sentBodies[0], '
'));

        foreach ($this->sentBodies as $body) {
            foreach (['evil', 'http://', 'https://evil', '0700000000', 'wave-ci'] as $needle) {
                $this->assertStringNotContainsString($needle, $body);
            }
        }
    }

    public function test_p1_welcome_sms_has_no_user_text_and_is_sent_once_per_number(): void
    {
        $this->captureSms();
        [$salon, $user, $subscription] = $this->registeredSalon(self::VICTIM, 'Salon Koffi');
        $user->forceFill(['name' => 'Koffi Wave Suspendu'])->save();

        event(new SalonRegistered($salon, $user, $subscription));

        // Deuxième inscription vers le même numéro (compte supprimé puis recréé, autre salon).
        [$otherSalon, , $otherSubscription] = $this->registeredSalon('2250102030405', 'Autre Salon');
        event(new SalonRegistered($otherSalon, $user, $otherSubscription));

        $this->assertCount(1, $this->sentBodies);
        $this->assertStringNotContainsString('Koffi', $this->sentBodies[0]);
        $this->assertStringNotContainsString('Salon Koffi', $this->sentBodies[0]);
        $this->assertStringContainsString('https://salono.ci/booking/'.$salon->slug, $this->sentBodies[0]);
    }

    public function test_p1_welcome_sms_has_a_global_hourly_cap(): void
    {
        config(['sms.welcome_hourly_limit' => 2]);
        $this->captureSms();

        foreach (['2250701000001', '2250701000002', '2250701000003'] as $i => $phone) {
            [$salon, $user, $subscription] = $this->registeredSalon($phone, 'Salon '.$i);
            event(new SalonRegistered($salon, $user, $subscription));
        }

        $this->assertCount(2, $this->sentBodies);
    }

    // --- P2 : limite par numéro contournable, bombardement de SMS ------------------------------

    public function test_p2_phone_normalization_never_truncates_extra_digits(): void
    {
        foreach (['22507070707070', '225070707070799', '2250707070707123456'] as $variant) {
            $this->assertNotSame(self::VICTIM, IvoryCoastPhone::normalize($variant));
            $this->assertFalse(IvoryCoastPhone::isValid($variant));
        }

        $this->assertFalse(IvoryCoastPhone::isValid('2250907070707'), 'Préfixe hors plan de numérotation CI.');
        $this->assertTrue(IvoryCoastPhone::isValid('+225 05 07 07 07 07'));
    }

    public function test_p2_booking_limiter_key_is_shared_by_equivalent_phone_formats(): void
    {
        $limiter = RateLimiter::limiter('public-booking');
        $keyFor = fn (string $phone): string => $limiter(Request::create('/api/booking/x', 'POST', ['client_phone' => $phone]))[1]->key;

        $keys = array_map($keyFor, ['2250707070707', '0707070707', '+225 07 07 07 07 07', '225-0707-070707']);

        $this->assertCount(1, array_unique($keys));
    }

    public function test_p2_sixth_booking_with_another_format_of_the_same_number_is_throttled(): void
    {
        Queue::fake();
        ['salon' => $salon] = $this->createBookableSalon();
        $formats = ['2250707070707', '0707070707', '+2250707070707', '225 07 07 07 07 07', '07-07-07-07-07', '+225.07.07.07.07.07'];

        $statuses = [];

        foreach ($formats as $phone) {
            // Corps invalide (pas de prestation) : seule la limite par numéro est rejouée.
            $statuses[] = $this->postJson('/api/booking/'.$salon->slug, ['client_phone' => $phone])->status();
        }

        $this->assertSame([422, 422, 422, 422, 422, 429], $statuses);
    }

    public function test_p2_booking_phone_with_extra_digits_is_rejected(): void
    {
        Queue::fake();
        ['salon' => $salon, 'stylist' => $stylist, 'service' => $service] = $this->createBookableSalon();

        $this->postJson('/api/booking/'.$salon->slug, [
            'client_phone' => '22507070707070',
            'client_name' => 'Koffi Client',
            'service_id' => $service->id,
            'user_id' => $stylist->id,
            'scheduled_at' => now()->addDay()->setTime(10, 0)->toIso8601String(),
        ])->assertUnprocessable()->assertJsonValidationErrors(['client_phone']);
    }

    public function test_p2_recipient_cap_applies_across_salons(): void
    {
        config(['sms.daily_limit_per_recipient' => 2]);
        $this->captureSms();
        $guard = app(SmsDeliveryGuard::class);

        $guard->sendOnce('test:a', 'salon-a', '+'.self::VICTIM, 'Rappel A');
        $guard->sendOnce('test:b', 'salon-b', '+'.self::VICTIM, 'Rappel B');
        $guard->sendOnce('test:c', 'salon-c', '+'.self::VICTIM, 'Rappel C');
        $guard->sendOnce('test:d', null, '+2250102030405', 'Autre destinataire');

        $this->assertSame(['Rappel A', 'Rappel B', 'Autre destinataire'], $this->sentBodies);
    }

    // --- P5 : données personnelles dans les logs ------------------------------------------------

    public function test_p5_masking_helpers(): void
    {
        $this->assertSame('+225*****0707', Pii::maskPhone('+2250707070707'));
        $this->assertSame('225*****0707', Pii::maskPhone('2250707070707'));
        $this->assertSame('k***@example.com', Pii::maskEmail('kouame@example.com'));
        $this->assertSame('k***@example.com', Pii::maskLogin('kouame@example.com'));
        $this->assertSame('Le numéro +225*****0707 de k***@example.com', Pii::redact('Le numéro +2250707070707 de kouame@example.com'));
    }

    public function test_p5_sms_and_welcome_email_logs_do_not_contain_clear_pii(): void
    {
        config(['sms.notifications_enabled' => false]);
        Mail::fake();
        Log::spy();

        app(TwilioSmsService::class)->send('+'.self::VICTIM, 'Bonjour');
        $user = User::factory()->create(['email' => 'kouame@example.com']);
        (new UserNotification($user))->sendWelcomeEmail();

        Log::shouldHaveReceived('debug')->withArgs(fn (string $message, array $context = []) => ($context['to'] ?? null) === '+225*****0707');
        Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context = []) => ($context['email'] ?? null) === 'k***@example.com');
    }

    private function captureSms(): void
    {
        $this->mock(TwilioSmsService::class)->shouldReceive('send')
            ->andReturnUsing(function (string $to, string $body): void {
                $this->sentBodies[] = $body;
            });
    }

    /** @return array{0: Salon, 1: User, 2: Subscription} */
    private function registeredSalon(string $phone, string $name): array
    {
        $salon = Salon::factory()->create(['name' => $name]);
        $user = User::query()->where('phone', $phone)->first()
            ?? User::factory()->create(['salon_id' => $salon->id, 'phone' => $phone]);
        $subscription = Subscription::query()->create([
            'salon_id' => $salon->id,
            'plan_id' => (Plan::query()->where('code', 'free')->first() ?? Plan::factory()->free()->create())->id,
            'status' => SubscriptionStatus::Trial,
            'is_trial' => true,
        ]);

        return [$salon, $user, $subscription];
    }

    private function registerPayload(array $overrides): array
    {
        return array_merge([
            'salon_name' => 'Salon Koffi Cocody',
            'city' => 'Abidjan',
            'whatsapp_number' => '2250708112233',
            'admin_name' => 'Koffi Atta',
            'phone' => '2250708112233',
            'password' => 'monmotdepasse8',
            'password_confirmation' => 'monmotdepasse8',
            'plan_code' => 'basic',
        ], $overrides);
    }
}
