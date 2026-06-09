<?php

namespace Tests\Feature\Salon;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SalonStaffRole;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Salon;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSalonSubscription;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class SalonClientTest extends TestCase
{
    use CreatesSalonSubscription;
    use RefreshDatabase;

    private Salon $salon;

    private string $staffToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salon = Salon::factory()->create();
        $this->createActiveSubscription($this->salon);
        $staff = User::factory()->create([
            'salon_id' => $this->salon->id,
            'role' => SalonStaffRole::Receptionist,
        ]);
        $this->staffToken = JWTAuth::fromUser($staff);
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->staffToken];
    }

    public function test_staff_can_list_salon_clients(): void
    {
        Client::factory()->create([
            'salon_id' => $this->salon->id,
            'name' => 'Client A',
        ]);

        $otherSalon = Salon::factory()->create();
        Client::factory()->create(['salon_id' => $otherSalon->id, 'name' => 'Client B']);

        $response = $this->getJson('/api/v1/clients', $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Client A');
    }

    public function test_staff_can_search_clients(): void
    {
        Client::factory()->create([
            'salon_id' => $this->salon->id,
            'name' => 'Fatou Diallo',
            'phone' => '2250708112233',
        ]);
        Client::factory()->create([
            'salon_id' => $this->salon->id,
            'name' => 'Aya Koné',
            'phone' => '2250708223344',
        ]);

        $response = $this->getJson('/api/v1/clients?search=Fatou', $this->authHeaders());

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Fatou Diallo');
    }

    public function test_staff_can_create_client(): void
    {
        $response = $this->postJson('/api/v1/clients', [
            'name' => 'Fatou Diallo',
            'phone' => '0748754918',
            'whatsapp_id' => '2250748754918',
        ], $this->authHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Fatou Diallo')
            ->assertJsonPath('data.phone', '2250748754918');

        $this->assertDatabaseHas('clients', [
            'salon_id' => $this->salon->id,
            'name' => 'Fatou Diallo',
            'phone' => '2250748754918',
            'total_visits' => 0,
        ]);
    }

    public function test_cannot_create_duplicate_phone_in_same_salon(): void
    {
        Client::factory()->create([
            'salon_id' => $this->salon->id,
            'phone' => '2250748754918',
        ]);

        $response = $this->postJson('/api/v1/clients', [
            'name' => 'Autre Client',
            'phone' => '0748754918',
        ], $this->authHeaders());

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_same_phone_allowed_in_different_salon(): void
    {
        $otherSalon = Salon::factory()->create();
        Client::factory()->create([
            'salon_id' => $otherSalon->id,
            'phone' => '2250748754918',
        ]);

        $response = $this->postJson('/api/v1/clients', [
            'name' => 'Fatou Diallo',
            'phone' => '0748754918',
        ], $this->authHeaders());

        $response->assertCreated();
    }

    public function test_staff_can_view_client_with_history(): void
    {
        $client = Client::factory()->create(['salon_id' => $this->salon->id]);
        $staff = User::query()->where('salon_id', $this->salon->id)->first();
        $service = Service::query()->create([
            'salon_id' => $this->salon->id,
            'name' => 'Coupe',
            'duration_min' => 30,
            'price' => 5000,
            'is_active' => true,
        ]);
        $appointment = Appointment::query()->create([
            'salon_id' => $this->salon->id,
            'client_id' => $client->id,
            'user_id' => $staff->id,
            'service_id' => $service->id,
            'scheduled_at' => now()->subDay(),
            'status' => AppointmentStatus::Completed,
            'notes' => 'Première visite',
        ]);
        Payment::query()->create([
            'salon_id' => $this->salon->id,
            'appointment_id' => $appointment->id,
            'client_id' => $client->id,
            'amount' => 5000,
            'method' => PaymentMethod::Cash,
            'status' => PaymentStatus::Paid,
            'paid_at' => now()->subDay(),
        ]);

        $response = $this->getJson("/api/v1/clients/{$client->id}", $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('data.id', $client->id)
            ->assertJsonCount(1, 'data.appointments')
            ->assertJsonCount(1, 'data.payments')
            ->assertJsonPath('data.appointments.0.service.name', 'Coupe')
            ->assertJsonPath('data.payments.0.amount', 5000);
    }

    public function test_staff_can_update_client(): void
    {
        $client = Client::factory()->create([
            'salon_id' => $this->salon->id,
            'name' => 'Old Name',
        ]);

        $response = $this->putJson("/api/v1/clients/{$client->id}", [
            'name' => 'New Name',
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Name');
    }

    public function test_cannot_access_client_from_another_salon(): void
    {
        $otherSalon = Salon::factory()->create();
        $client = Client::factory()->create(['salon_id' => $otherSalon->id]);

        $response = $this->getJson("/api/v1/clients/{$client->id}", $this->authHeaders());

        $response->assertNotFound();
    }

    public function test_unauthenticated_cannot_access_clients(): void
    {
        $response = $this->getJson('/api/v1/clients');

        $response->assertUnauthorized();
    }
}
