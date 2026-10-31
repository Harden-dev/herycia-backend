<?php

namespace Tests\Feature\Queue;

use App\Enums\AppointmentStatus;
use App\Enums\QueueEntrySource;
use App\Enums\SalonStaffRole;
use App\Jobs\Sms\SendQueueSoonSms;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\QueueEntry;
use App\Models\Salon;
use App\Models\Service;
use App\Models\User;
use App\Services\Auth\JwtService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\CreatesBookableSalon;
use Tests\TestCase;

/**
 * File d'attente V2 : clients sans rendez-vous, premier coiffeur disponible, absents automatiques,
 * SMS « bientôt », réaffectation et tolérance réglable. Samedi 10 octobre 2026, 10:00.
 */
class QueueV2Test extends TestCase
{
    use CreatesBookableSalon;
    use RefreshDatabase;

    private const KEY = 'cle-arrivee-test-v2-1234567890ab';

    private Salon $salon;

    private User $koffi;

    private User $aya;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-10 10:00:00'));

        ['salon' => $this->salon, 'stylist' => $this->koffi, 'service' => $this->service] = $this->createBookableSalon();
        $this->salon->forceFill(['checkin_key' => self::KEY])->save();

        $this->aya = User::factory()->create([
            'salon_id' => $this->salon->id,
            'role' => SalonStaffRole::Stylist,
            'is_active' => true,
            'name' => 'Aya',
        ]);
    }

    private function appointment(User $stylist, string $at, string $phone, array $attributes = []): Appointment
    {
        $client = Client::factory()->create(['salon_id' => $this->salon->id, 'phone' => $phone]);

        return Appointment::withoutEvents(fn () => Appointment::query()->create(array_merge([
            'salon_id' => $this->salon->id,
            'client_id' => $client->id,
            'user_id' => $stylist->id,
            'service_id' => $this->service->id,
            'scheduled_at' => Carbon::parse($at),
            'status' => AppointmentStatus::Confirmed,
            'tracking_token' => 'trk'.substr($phone, -6),
        ], $attributes)));
    }

    private function walkIn(array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/checkin/'.$this->salon->slug.'/walk-in', array_merge([
            'key' => self::KEY,
            'service_id' => $this->service->id,
            'name' => 'Client Sans RDV',
            'phone' => '0707999001',
        ], $payload));
    }

    private function staffHeaders(): array
    {
        $admin = User::factory()->create(['salon_id' => $this->salon->id, 'role' => SalonStaffRole::Admin, 'is_active' => true]);

        return ['Authorization' => 'Bearer '.app(JwtService::class)->createToken($admin)];
    }

    public function test_walk_in_options_list_services_and_first_available_stylist(): void
    {
        // Koffi a un client à l'heure à 10:05 : Aya est libre tout de suite.
        $busy = $this->appointment($this->koffi, '2026-10-10 10:05', '2250707000101');
        $this->postJson('/api/checkin/'.$this->salon->slug, ['key' => self::KEY, 'tracking_token' => $busy->tracking_token])->assertOk();

        $response = $this->getJson('/api/checkin/'.$this->salon->slug.'/walk-in?key='.self::KEY.'&service_id='.$this->service->id)
            ->assertOk();

        $response->assertJsonPath('data.services.0.id', $this->service->id)
            ->assertJsonPath('data.options.first_available.stylist.id', $this->aya->id)
            ->assertJsonPath('data.options.first_available.position', 1)
            ->assertJsonCount(2, 'data.options.stylists');
    }

    public function test_walk_in_joins_first_available_stylist_after_the_last(): void
    {
        $busy = $this->appointment($this->aya, '2026-10-10 10:05', '2250707000102');
        $this->postJson('/api/checkin/'.$this->salon->slug, ['key' => self::KEY, 'tracking_token' => $busy->tracking_token]);

        $response = $this->walkIn([])->assertOk();

        $response->assertJsonPath('data.status', 'queued')
            ->assertJsonPath('data.entry.source', 'walk_in')
            ->assertJsonPath('data.entry.stylist.name', $this->koffi->name)
            ->assertJsonPath('data.entry.position', 1);
    }

    public function test_walk_in_with_chosen_stylist_goes_after_on_time_client(): void
    {
        $onTime = $this->appointment($this->koffi, '2026-10-10 10:05', '2250707000103');
        $this->postJson('/api/checkin/'.$this->salon->slug, ['key' => self::KEY, 'tracking_token' => $onTime->tracking_token]);

        $response = $this->walkIn(['stylist_id' => $this->koffi->id])->assertOk();

        $response->assertJsonPath('data.entry.position', 2);
        $this->assertSame('10:30', Carbon::parse($response->json('data.entry.estimated_start_at'))->format('H:i'));
    }

    public function test_walk_in_is_idempotent_and_never_renames_existing_client(): void
    {
        Client::factory()->create(['salon_id' => $this->salon->id, 'phone' => '2250707999001', 'name' => 'Vrai Nom']);

        $first = $this->walkIn(['name' => 'Nom Usurpé'])->json('data.entry.tracking_token');
        $second = $this->walkIn(['name' => 'Nom Usurpé'])->json('data.entry.tracking_token');

        $this->assertSame($first, $second);
        $this->assertSame(1, QueueEntry::query()->count());
        $this->assertSame('Vrai Nom', Client::query()->where('phone', '2250707999001')->value('name'));
    }

    public function test_walk_in_requires_salon_qr_key(): void
    {
        $this->walkIn(['key' => 'mauvaise'])->assertForbidden()->assertJsonPath('code', 'invalid_checkin_key');
    }

    public function test_staff_can_add_walk_in_from_front_desk(): void
    {
        $response = $this->postJson('/api/v1/queue/walk-in', [
            'service_id' => $this->service->id,
            'stylist_id' => $this->aya->id,
            'name' => 'Client Accueil',
            'phone' => '0707999002',
        ], $this->staffHeaders())->assertOk();

        $response->assertJsonPath('data.entry.source', 'walk_in')
            ->assertJsonPath('data.entry.stylist.name', 'Aya');
    }

    public function test_auto_no_show_marks_only_todays_unarrived_appointments_after_delay(): void
    {
        $old = $this->appointment($this->koffi, '2026-10-10 08:30', '2250707000104');
        $recent = $this->appointment($this->koffi, '2026-10-10 09:30', '2250707000105');
        $yesterday = $this->appointment($this->koffi, '2026-10-09 09:00', '2250707000106');

        $this->artisan('queue:mark-no-shows')->assertSuccessful();

        $this->assertSame(AppointmentStatus::NoShow, $old->fresh()->status);
        $this->assertSame(AppointmentStatus::Confirmed, $recent->fresh()->status);
        $this->assertSame(AppointmentStatus::Confirmed, $yesterday->fresh()->status);
    }

    public function test_auto_no_show_client_can_still_arrive_late_and_join_queue(): void
    {
        $appointment = $this->appointment($this->koffi, '2026-10-10 08:30', '2250707000107');
        $this->artisan('queue:mark-no-shows');

        $this->postJson('/api/checkin/'.$this->salon->slug, ['key' => self::KEY, 'phone' => '0707000107'])
            ->assertOk()
            ->assertJsonPath('data.status', 'late');

        $this->postJson('/api/checkin/'.$this->salon->slug.'/late-choice', [
            'key' => self::KEY,
            'tracking_token' => $appointment->tracking_token,
            'choice' => 'queue',
        ])->assertOk()->assertJsonPath('data.status', 'queued');

        $this->assertSame(AppointmentStatus::Confirmed, $appointment->fresh()->status);
    }

    public function test_notify_soon_sends_one_sms_to_the_next_client_only(): void
    {
        Queue::fake();

        $this->walkIn(['phone' => '0707999011', 'stylist_id' => $this->koffi->id]);
        $this->walkIn(['phone' => '0707999012', 'stylist_id' => $this->koffi->id]);
        $this->walkIn(['phone' => '0707999013', 'stylist_id' => $this->koffi->id]);

        $this->artisan('queue:notify-soon')->assertSuccessful();
        $this->artisan('queue:notify-soon')->assertSuccessful();

        // Le 1er passe maintenant (prochain) ; le 2e à 10:30, le 3e à 11:00 : trop loin.
        Queue::assertPushed(SendQueueSoonSms::class, 1);
        $this->assertSame(1, QueueEntry::query()->whereNotNull('soon_notified_at')->count());
    }

    public function test_reassign_moves_client_and_appointment_to_another_stylist(): void
    {
        $appointment = $this->appointment($this->koffi, '2026-10-10 10:10', '2250707000108');
        $this->postJson('/api/checkin/'.$this->salon->slug, ['key' => self::KEY, 'tracking_token' => $appointment->tracking_token]);
        $entryId = QueueEntry::query()->value('id');
        $headers = $this->staffHeaders();

        $this->patchJson("/api/v1/queue/{$entryId}/reassign", ['stylist_id' => $this->aya->id], $headers)
            ->assertOk()
            ->assertJsonPath('data.status', 'waiting');

        $this->assertSame($this->aya->id, QueueEntry::query()->find($entryId)->user_id);
        $this->assertSame($this->aya->id, $appointment->fresh()->user_id);

        $otherSalonStylist = User::factory()->create(['role' => SalonStaffRole::Stylist]);
        $this->patchJson("/api/v1/queue/{$entryId}/reassign", ['stylist_id' => $otherSalonStylist->id], $headers)
            ->assertStatus(422)
            ->assertJsonPath('code', 'invalid_stylist');
    }

    public function test_late_tolerance_is_configurable_by_admin(): void
    {
        $appointment = $this->appointment($this->koffi, '2026-10-10 09:40', '2250707000109');

        $this->putJson('/api/v1/salon', ['late_tolerance_minutes' => 30], $this->staffHeaders())
            ->assertOk()
            ->assertJsonPath('data.late_tolerance_minutes', 30);

        // 20 min de retard : dans la tolérance de 30 min.
        $this->postJson('/api/checkin/'.$this->salon->slug, ['key' => self::KEY, 'tracking_token' => $appointment->tracking_token])
            ->assertOk()
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonPath('data.entry.source', QueueEntrySource::Appointment->value);
    }
}
