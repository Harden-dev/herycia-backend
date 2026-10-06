<?php

namespace Tests\Feature\Queue;

use App\Enums\AppointmentStatus;
use App\Enums\QueueEntryStatus;
use App\Enums\SalonStaffRole;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\QueueEntry;
use App\Models\Salon;
use App\Models\Service;
use App\Models\User;
use App\Services\Auth\JwtService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBookableSalon;
use Tests\TestCase;

/**
 * File d'attente V1 : arrivée, retard (tolérance 15 min), choix du client, suivi et back-office.
 * Les tests se déroulent un samedi : le salon est fermé le dimanche.
 */
class QueueCheckInTest extends TestCase
{
    use CreatesBookableSalon;
    use RefreshDatabase;

    private const KEY = 'cle-arrivee-test-1234567890abcdef';

    private Salon $salon;

    private User $stylist;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Samedi 10 octobre 2026, 10:00
        $this->travelTo(Carbon::parse('2026-10-10 10:00:00'));

        ['salon' => $this->salon, 'stylist' => $this->stylist, 'service' => $this->service] = $this->createBookableSalon();
        $this->salon->forceFill(['checkin_key' => self::KEY])->save();
    }

    private function appointment(string $time, string $phone, array $attributes = []): Appointment
    {
        $client = Client::factory()->create([
            'salon_id' => $this->salon->id,
            'phone' => $phone,
            'name' => 'Client '.$time,
        ]);

        return Appointment::withoutEvents(fn () => Appointment::query()->create(array_merge([
            'salon_id' => $this->salon->id,
            'client_id' => $client->id,
            'user_id' => $this->stylist->id,
            'service_id' => $this->service->id,
            'scheduled_at' => Carbon::parse('2026-10-10 '.$time),
            'status' => AppointmentStatus::Confirmed,
            'tracking_token' => 'trk'.str_replace(':', '', $time).substr($phone, -4),
        ], $attributes)));
    }

    private function checkIn(array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/checkin/'.$this->salon->slug, array_merge(['key' => self::KEY], $payload));
    }

    private function staffHeaders(): array
    {
        $admin = User::factory()->create([
            'salon_id' => $this->salon->id,
            'role' => SalonStaffRole::Admin,
            'is_active' => true,
        ]);

        return ['Authorization' => 'Bearer '.app(JwtService::class)->createToken($admin)];
    }

    public function test_on_time_client_joins_queue_with_position_and_estimate(): void
    {
        $this->appointment('10:10', '2250707000001');

        $response = $this->checkIn(['phone' => '0707000001'])->assertOk();

        $response->assertJsonPath('data.status', 'queued')
            ->assertJsonPath('data.entry.status', 'waiting')
            ->assertJsonPath('data.entry.source', 'appointment')
            ->assertJsonPath('data.entry.position', 1)
            ->assertJsonPath('data.entry.people_ahead', 0);

        // Coiffeur libre : passage tout de suite (client en avance, rien ne bloque).
        $this->assertSame('10:00', Carbon::parse($response->json('data.entry.estimated_start_at'))->format('H:i'));
        $this->assertNotNull(Appointment::query()->first()->checked_in_at);
    }

    public function test_client_within_tolerance_is_not_late(): void
    {
        $this->appointment('09:46', '2250707000002');

        $this->checkIn(['phone' => '0707000002'])->assertOk()->assertJsonPath('data.status', 'queued');
    }

    public function test_check_in_requires_the_salon_qr_key(): void
    {
        $this->appointment('10:10', '2250707000003');

        $this->postJson('/api/checkin/'.$this->salon->slug, ['key' => 'mauvaise-cle', 'phone' => '0707000003'])
            ->assertForbidden()
            ->assertJsonPath('code', 'invalid_checkin_key');
    }

    public function test_check_in_is_idempotent(): void
    {
        $this->appointment('10:10', '2250707000004');

        $first = $this->checkIn(['phone' => '0707000004'])->json('data.entry.tracking_token');
        $second = $this->checkIn(['phone' => '0707000004'])->json('data.entry.tracking_token');

        $this->assertSame($first, $second);
        $this->assertSame(1, QueueEntry::query()->count());
    }

    public function test_unknown_phone_returns_not_found(): void
    {
        $this->checkIn(['phone' => '0700000000'])->assertNotFound()->assertJsonPath('code', 'appointment_not_found');
    }

    public function test_late_client_gets_both_options_without_being_queued(): void
    {
        $late = $this->appointment('09:30', '2250707000005');

        $response = $this->checkIn(['tracking_token' => $late->tracking_token])->assertOk();

        $response->assertJsonPath('data.status', 'late')
            ->assertJsonPath('data.late_tolerance_minutes', 15)
            ->assertJsonPath('data.options.queue.position', 1);

        // Même heure, autre jour : dimanche fermé → lundi 12 octobre à 09:30.
        $this->assertSame(
            '2026-10-12 09:30',
            Carbon::parse($response->json('data.options.reschedule.scheduled_at'))->format('Y-m-d H:i'),
        );
        $this->assertSame(0, QueueEntry::query()->count());
    }

    public function test_late_client_choosing_queue_goes_after_on_time_clients(): void
    {
        $onTime = $this->appointment('10:05', '2250707000006');
        $late = $this->appointment('09:30', '2250707000007');

        $this->checkIn(['tracking_token' => $onTime->tracking_token])->assertOk();

        $response = $this->postJson('/api/checkin/'.$this->salon->slug.'/late-choice', [
            'key' => self::KEY,
            'tracking_token' => $late->tracking_token,
            'choice' => 'queue',
        ])->assertOk();

        $response->assertJsonPath('data.status', 'queued')
            ->assertJsonPath('data.entry.source', 'late')
            ->assertJsonPath('data.entry.position', 2);

        // Le client à l'heure (30 min) passe d'abord ; le retardataire à 10:30.
        $this->assertSame('10:30', Carbon::parse($response->json('data.entry.estimated_start_at'))->format('H:i'));
    }

    public function test_late_client_choosing_reschedule_keeps_same_time_another_day(): void
    {
        $late = $this->appointment('09:30', '2250707000008');
        // Le coiffeur est déjà pris lundi à 09:30 : prochain jour libre mardi.
        $this->appointment('09:30', '2250707000009', ['scheduled_at' => Carbon::parse('2026-10-12 09:30')]);

        $response = $this->postJson('/api/checkin/'.$this->salon->slug.'/late-choice', [
            'key' => self::KEY,
            'tracking_token' => $late->tracking_token,
            'choice' => 'reschedule',
        ])->assertOk();

        $response->assertJsonPath('data.status', 'rescheduled');

        $late->refresh();
        $this->assertSame('2026-10-13 09:30', $late->scheduled_at->format('Y-m-d H:i'));
        $this->assertSame('2026-10-10 09:30', $late->rescheduled_from->format('Y-m-d H:i'));
        $this->assertSame(AppointmentStatus::Confirmed, $late->status);
        $this->assertSame(0, QueueEntry::query()->count());
    }

    public function test_public_tracking_shows_position_without_personal_data(): void
    {
        $this->appointment('10:10', '2250707000010');
        $token = $this->checkIn(['phone' => '0707000010'])->json('data.entry.tracking_token');

        $response = $this->getJson('/api/file/'.$token)->assertOk();

        $response->assertJsonPath('data.position', 1)
            ->assertJsonPath('data.stylist.name', $this->stylist->name)
            ->assertJsonMissingPath('data.client.phone');
    }

    public function test_staff_board_check_in_and_full_service_flow(): void
    {
        $headers = $this->staffHeaders();
        $appointment = $this->appointment('10:10', '2250707000011');
        $visitsBefore = $appointment->client->total_visits;

        $board = $this->getJson('/api/v1/queue/board', $headers)->assertOk();
        $board->assertJsonPath('data.stylists.0.expected.0.id', $appointment->id)
            ->assertJsonPath('data.stylists.0.expected.0.is_late', false);

        $this->postJson('/api/v1/queue/check-in', ['appointment_id' => $appointment->id], $headers)
            ->assertOk()
            ->assertJsonPath('data.status', 'queued');

        $entryId = $this->getJson('/api/v1/queue/board', $headers)->json('data.stylists.0.queue.0.id');

        $this->patchJson("/api/v1/queue/{$entryId}/call", [], $headers)->assertOk()->assertJsonPath('data.status', 'called');
        $this->patchJson("/api/v1/queue/{$entryId}/start", [], $headers)->assertOk()->assertJsonPath('data.status', 'in_service');
        $this->assertSame(AppointmentStatus::InProgress, $appointment->fresh()->status);

        $this->patchJson("/api/v1/queue/{$entryId}/done", [], $headers)->assertOk()->assertJsonPath('data.status', 'done');
        $this->assertSame(AppointmentStatus::Completed, $appointment->fresh()->status);
        $this->assertSame($visitsBefore + 1, $appointment->client->fresh()->total_visits);

        // Transition impossible : déjà terminé.
        $this->patchJson("/api/v1/queue/{$entryId}/start", [], $headers)->assertStatus(409);
    }

    public function test_stylist_cannot_start_two_clients_at_once(): void
    {
        $headers = $this->staffHeaders();
        $first = $this->appointment('10:05', '2250707000012');
        $second = $this->appointment('10:20', '2250707000013');

        $this->checkIn(['tracking_token' => $first->tracking_token]);
        $this->checkIn(['tracking_token' => $second->tracking_token]);
        [$a, $b] = QueueEntry::query()->orderBy('priority_at')->pluck('id')->all();

        $this->patchJson("/api/v1/queue/{$a}/start", [], $headers)->assertOk();
        $this->patchJson("/api/v1/queue/{$b}/start", [], $headers)
            ->assertStatus(409)
            ->assertJsonPath('code', 'stylist_busy');
    }

    public function test_no_show_marks_appointment_absent(): void
    {
        $headers = $this->staffHeaders();
        $appointment = $this->appointment('10:10', '2250707000014');
        $this->checkIn(['phone' => '0707000014']);
        $entryId = QueueEntry::query()->value('id');

        $this->patchJson("/api/v1/queue/{$entryId}/no-show", [], $headers)->assertOk();

        $this->assertSame(QueueEntryStatus::Cancelled, QueueEntry::query()->find($entryId)->status);
        $this->assertSame(AppointmentStatus::NoShow, $appointment->fresh()->status);
    }

    public function test_staff_of_another_salon_cannot_act_on_entry(): void
    {
        $this->appointment('10:10', '2250707000015');
        $this->checkIn(['phone' => '0707000015']);
        $entryId = QueueEntry::query()->value('id');

        $otherSalon = Salon::factory()->create(['is_active' => true]);
        \App\Models\Subscription::query()->create([
            'salon_id' => $otherSalon->id,
            'plan_id' => \App\Models\Subscription::query()->value('plan_id'),
            'status' => \App\Enums\SubscriptionStatus::Trial,
            'is_trial' => true,
        ]);
        $otherAdmin = User::factory()->create(['salon_id' => $otherSalon->id, 'role' => SalonStaffRole::Admin]);
        $headers = ['Authorization' => 'Bearer '.app(JwtService::class)->createToken($otherAdmin)];

        $this->patchJson("/api/v1/queue/{$entryId}/call", [], $headers)->assertNotFound();
        $this->assertSame(QueueEntryStatus::Waiting, QueueEntry::query()->find($entryId)->status);
    }

    public function test_checkin_qr_can_be_regenerated_and_old_key_stops_working(): void
    {
        $headers = $this->staffHeaders();
        $this->appointment('10:10', '2250707000016');

        $first = $this->getJson('/api/v1/salon/checkin-qr', $headers)->assertOk();
        $this->assertStringContainsString('/arrivee/'.$this->salon->slug.'?k='.self::KEY, $first->json('data.checkin_url'));
        $this->assertStringStartsWith('data:image/', $first->json('data.qr_code'));

        $this->postJson('/api/v1/salon/checkin-qr/regenerate', [], $headers)->assertOk();

        $this->checkIn(['phone' => '0707000016'])->assertForbidden();
    }
}
