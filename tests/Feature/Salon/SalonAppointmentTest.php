<?php

namespace Tests\Feature\Salon;

use App\Enums\AppointmentStatus;
use App\Enums\SalonStaffRole;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Salon;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSalonSubscription;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class SalonAppointmentTest extends TestCase
{
    use CreatesSalonSubscription;
    use RefreshDatabase;

    private Salon $salon;

    private User $stylist;

    private Client $client;

    private Service $service;

    private string $staffToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salon = Salon::factory()->create();
        $this->createActiveSubscription($this->salon);
        $this->stylist = User::factory()->create([
            'salon_id' => $this->salon->id,
            'role' => SalonStaffRole::Stylist,
            'is_active' => true,
        ]);
        $receptionist = User::factory()->create([
            'salon_id' => $this->salon->id,
            'role' => SalonStaffRole::Receptionist,
        ]);
        $this->client = Client::factory()->create(['salon_id' => $this->salon->id]);
        $this->service = Service::query()->create([
            'salon_id' => $this->salon->id,
            'name' => 'Coupe',
            'duration_min' => 30,
            'price' => 5000,
            'is_active' => true,
        ]);
        $this->staffToken = JWTAuth::fromUser($receptionist);
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->staffToken];
    }

    public function test_staff_can_list_appointments_for_day(): void
    {
        Appointment::query()->create([
            'salon_id' => $this->salon->id,
            'client_id' => $this->client->id,
            'user_id' => $this->stylist->id,
            'service_id' => $this->service->id,
            'scheduled_at' => now()->setTime(10, 0),
            'status' => AppointmentStatus::Confirmed,
        ]);
        Appointment::query()->create([
            'salon_id' => $this->salon->id,
            'client_id' => $this->client->id,
            'user_id' => $this->stylist->id,
            'service_id' => $this->service->id,
            'scheduled_at' => now()->addDay()->setTime(10, 0),
            'status' => AppointmentStatus::Confirmed,
        ]);

        $response = $this->getJson('/api/v1/appointments?date='.now()->toDateString(), $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');
    }

    public function test_staff_can_list_appointments_for_week_range(): void
    {
        Appointment::query()->create([
            'salon_id' => $this->salon->id,
            'client_id' => $this->client->id,
            'user_id' => $this->stylist->id,
            'service_id' => $this->service->id,
            'scheduled_at' => now()->addDays(2)->setTime(10, 0),
            'status' => AppointmentStatus::Confirmed,
        ]);
        Appointment::query()->create([
            'salon_id' => $this->salon->id,
            'client_id' => $this->client->id,
            'user_id' => $this->stylist->id,
            'service_id' => $this->service->id,
            'scheduled_at' => now()->addDays(10)->setTime(10, 0),
            'status' => AppointmentStatus::Confirmed,
        ]);

        $from = now()->toDateString();
        $to = now()->addDays(7)->toDateString();

        $response = $this->getJson("/api/v1/appointments?from={$from}&to={$to}", $this->authHeaders());

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_staff_can_filter_appointments_by_stylist(): void
    {
        $otherStylist = User::factory()->create([
            'salon_id' => $this->salon->id,
            'role' => SalonStaffRole::Stylist,
        ]);

        Appointment::query()->create([
            'salon_id' => $this->salon->id,
            'client_id' => $this->client->id,
            'user_id' => $this->stylist->id,
            'service_id' => $this->service->id,
            'scheduled_at' => now()->setTime(10, 0),
            'status' => AppointmentStatus::Confirmed,
        ]);
        Appointment::query()->create([
            'salon_id' => $this->salon->id,
            'client_id' => $this->client->id,
            'user_id' => $otherStylist->id,
            'service_id' => $this->service->id,
            'scheduled_at' => now()->setTime(14, 0),
            'status' => AppointmentStatus::Confirmed,
        ]);

        $response = $this->getJson(
            '/api/v1/appointments?date='.now()->toDateString().'&user_id='.$this->stylist->id,
            $this->authHeaders(),
        );

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.staff.id', $this->stylist->id);
    }

    public function test_staff_can_create_appointment(): void
    {
        $scheduledAt = now()->addDay()->setTime(11, 30)->toIso8601String();

        $response = $this->postJson('/api/v1/appointments', [
            'client_id' => $this->client->id,
            'user_id' => $this->stylist->id,
            'service_id' => $this->service->id,
            'scheduled_at' => $scheduledAt,
            'notes' => 'Première visite',
        ], $this->authHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.status', AppointmentStatus::Pending->value)
            ->assertJsonPath('data.client.id', $this->client->id);

        $this->assertDatabaseHas('appointments', [
            'salon_id' => $this->salon->id,
            'client_id' => $this->client->id,
            'user_id' => $this->stylist->id,
            'status' => AppointmentStatus::Pending->value,
        ]);
    }

    public function test_cannot_create_appointment_with_foreign_salon_client(): void
    {
        $otherSalon = Salon::factory()->create();
        $foreignClient = Client::factory()->create(['salon_id' => $otherSalon->id]);

        $response = $this->postJson('/api/v1/appointments', [
            'client_id' => $foreignClient->id,
            'user_id' => $this->stylist->id,
            'service_id' => $this->service->id,
            'scheduled_at' => now()->addDay()->toIso8601String(),
        ], $this->authHeaders());

        $response->assertBadRequest();
    }

    public function test_staff_can_view_appointment_detail(): void
    {
        $appointment = Appointment::query()->create([
            'salon_id' => $this->salon->id,
            'client_id' => $this->client->id,
            'user_id' => $this->stylist->id,
            'service_id' => $this->service->id,
            'scheduled_at' => now()->addDay(),
            'status' => AppointmentStatus::Confirmed,
            'notes' => 'Test note',
        ]);

        $response = $this->getJson("/api/v1/appointments/{$appointment->id}", $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('data.id', $appointment->id)
            ->assertJsonPath('data.service.name', 'Coupe')
            ->assertJsonPath('data.notes', 'Test note');
    }

    public function test_staff_can_update_appointment_status(): void
    {
        $appointment = Appointment::query()->create([
            'salon_id' => $this->salon->id,
            'client_id' => $this->client->id,
            'user_id' => $this->stylist->id,
            'service_id' => $this->service->id,
            'scheduled_at' => now()->addDay(),
            'status' => AppointmentStatus::Confirmed,
        ]);

        $response = $this->putJson("/api/v1/appointments/{$appointment->id}/status", [
            'status' => AppointmentStatus::InProgress->value,
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('data.status', AppointmentStatus::InProgress->value);
    }

    public function test_completing_appointment_updates_client_visits(): void
    {
        $this->client->update(['total_visits' => 2, 'last_visit_at' => null]);

        $scheduledAt = now()->subHour();
        $appointment = Appointment::query()->create([
            'salon_id' => $this->salon->id,
            'client_id' => $this->client->id,
            'user_id' => $this->stylist->id,
            'service_id' => $this->service->id,
            'scheduled_at' => $scheduledAt,
            'status' => AppointmentStatus::InProgress,
        ]);

        $response = $this->putJson("/api/v1/appointments/{$appointment->id}/status", [
            'status' => AppointmentStatus::Completed->value,
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('data.status', AppointmentStatus::Completed->value);

        $this->client->refresh();
        $this->assertSame(3, $this->client->total_visits);
        $this->assertNotNull($this->client->last_visit_at);
    }

    public function test_staff_can_cancel_appointment(): void
    {
        $appointment = Appointment::query()->create([
            'salon_id' => $this->salon->id,
            'client_id' => $this->client->id,
            'user_id' => $this->stylist->id,
            'service_id' => $this->service->id,
            'scheduled_at' => now()->addDay(),
            'status' => AppointmentStatus::Confirmed,
        ]);

        $response = $this->deleteJson("/api/v1/appointments/{$appointment->id}", [], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('data.status', AppointmentStatus::Cancelled->value);
    }

    public function test_cannot_cancel_completed_appointment(): void
    {
        $appointment = Appointment::query()->create([
            'salon_id' => $this->salon->id,
            'client_id' => $this->client->id,
            'user_id' => $this->stylist->id,
            'service_id' => $this->service->id,
            'scheduled_at' => now()->subHour(),
            'status' => AppointmentStatus::Completed,
        ]);

        $response = $this->deleteJson("/api/v1/appointments/{$appointment->id}", [], $this->authHeaders());

        $response->assertBadRequest();
    }

    public function test_cannot_access_appointment_from_another_salon(): void
    {
        $otherSalon = Salon::factory()->create();
        $appointment = Appointment::query()->create([
            'salon_id' => $otherSalon->id,
            'client_id' => Client::factory()->create(['salon_id' => $otherSalon->id])->id,
            'user_id' => User::factory()->create(['salon_id' => $otherSalon->id])->id,
            'service_id' => Service::query()->create([
                'salon_id' => $otherSalon->id,
                'name' => 'Autre',
                'duration_min' => 30,
                'price' => 3000,
                'is_active' => true,
            ])->id,
            'scheduled_at' => now()->addDay(),
            'status' => AppointmentStatus::Pending,
        ]);

        $response = $this->getJson("/api/v1/appointments/{$appointment->id}", $this->authHeaders());

        $response->assertNotFound();
    }

    public function test_unauthenticated_cannot_access_appointments(): void
    {
        $response = $this->getJson('/api/v1/appointments');

        $response->assertUnauthorized();
    }
}
