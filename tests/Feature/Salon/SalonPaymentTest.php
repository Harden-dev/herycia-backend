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

class SalonPaymentTest extends TestCase
{
    use CreatesSalonSubscription;
    use RefreshDatabase;

    private Salon $salon;

    private Appointment $appointment;

    private string $adminToken;

    private string $receptionistToken;

    private string $stylistToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salon = Salon::factory()->create();
        $this->createActiveSubscription($this->salon);
        $stylist = User::factory()->create([
            'salon_id' => $this->salon->id,
            'role' => SalonStaffRole::Stylist,
        ]);
        $admin = User::factory()->create([
            'salon_id' => $this->salon->id,
            'role' => SalonStaffRole::Admin,
        ]);
        $receptionist = User::factory()->create([
            'salon_id' => $this->salon->id,
            'role' => SalonStaffRole::Receptionist,
        ]);
        $client = Client::factory()->create(['salon_id' => $this->salon->id]);
        $service = Service::query()->create([
            'salon_id' => $this->salon->id,
            'name' => 'Coupe',
            'duration_min' => 30,
            'price' => 5000,
            'is_active' => true,
        ]);
        $this->appointment = Appointment::query()->create([
            'salon_id' => $this->salon->id,
            'client_id' => $client->id,
            'user_id' => $stylist->id,
            'service_id' => $service->id,
            'scheduled_at' => now(),
            'status' => AppointmentStatus::Completed,
        ]);

        $this->adminToken = JWTAuth::fromUser($admin);
        $this->receptionistToken = JWTAuth::fromUser($receptionist);
        $this->stylistToken = JWTAuth::fromUser($stylist);
    }

    public function test_receptionist_can_list_payments(): void
    {
        Payment::query()->create([
            'salon_id' => $this->salon->id,
            'appointment_id' => $this->appointment->id,
            'client_id' => $this->appointment->client_id,
            'amount' => 5000,
            'method' => PaymentMethod::Cash,
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/payments', [
            'Authorization' => 'Bearer '.$this->receptionistToken,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');
    }

    public function test_receptionist_can_filter_payments_by_date_and_method(): void
    {
        Payment::query()->create([
            'salon_id' => $this->salon->id,
            'appointment_id' => $this->appointment->id,
            'client_id' => $this->appointment->client_id,
            'amount' => 5000,
            'method' => PaymentMethod::Cash,
            'status' => PaymentStatus::Paid,
            'paid_at' => now()->setTime(10, 0),
        ]);

        $otherAppointment = Appointment::query()->create([
            'salon_id' => $this->salon->id,
            'client_id' => $this->appointment->client_id,
            'user_id' => $this->appointment->user_id,
            'service_id' => $this->appointment->service_id,
            'scheduled_at' => now()->addDay(),
            'status' => AppointmentStatus::Completed,
        ]);

        Payment::query()->create([
            'salon_id' => $this->salon->id,
            'appointment_id' => $otherAppointment->id,
            'client_id' => $otherAppointment->client_id,
            'amount' => 7000,
            'method' => PaymentMethod::MobileMoney,
            'status' => PaymentStatus::Paid,
            'mobile_money_ref' => 'MM123',
            'paid_at' => now()->addDay(),
        ]);

        $response = $this->getJson(
            '/api/v1/payments?date='.now()->toDateString().'&method=cash',
            ['Authorization' => 'Bearer '.$this->receptionistToken],
        );

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.method', PaymentMethod::Cash->value);
    }

    public function test_receptionist_can_record_payment(): void
    {
        $response = $this->postJson('/api/v1/payments', [
            'appointment_id' => $this->appointment->id,
            'amount' => 5000,
            'method' => PaymentMethod::Cash->value,
        ], ['Authorization' => 'Bearer '.$this->receptionistToken]);

        $response->assertCreated()
            ->assertJsonPath('data.amount', 5000)
            ->assertJsonPath('data.status', PaymentStatus::Paid->value);

        $this->assertDatabaseHas('payments', [
            'salon_id' => $this->salon->id,
            'appointment_id' => $this->appointment->id,
            'amount' => 5000,
            'status' => PaymentStatus::Paid->value,
        ]);
    }

    public function test_mobile_money_payment_requires_reference(): void
    {
        $response = $this->postJson('/api/v1/payments', [
            'appointment_id' => $this->appointment->id,
            'amount' => 5000,
            'method' => PaymentMethod::MobileMoney->value,
        ], ['Authorization' => 'Bearer '.$this->receptionistToken]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['mobile_money_ref']);
    }

    public function test_cannot_record_duplicate_payment_for_appointment(): void
    {
        Payment::query()->create([
            'salon_id' => $this->salon->id,
            'appointment_id' => $this->appointment->id,
            'client_id' => $this->appointment->client_id,
            'amount' => 5000,
            'method' => PaymentMethod::Cash,
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/payments', [
            'appointment_id' => $this->appointment->id,
            'amount' => 5000,
            'method' => PaymentMethod::Cash->value,
        ], ['Authorization' => 'Bearer '.$this->receptionistToken]);

        $response->assertBadRequest();
    }

    public function test_stylist_cannot_access_payments(): void
    {
        $response = $this->getJson('/api/v1/payments', [
            'Authorization' => 'Bearer '.$this->stylistToken,
        ]);

        $response->assertForbidden();
    }

    public function test_admin_can_view_payment_summary(): void
    {
        Payment::query()->create([
            'salon_id' => $this->salon->id,
            'appointment_id' => $this->appointment->id,
            'client_id' => $this->appointment->client_id,
            'amount' => 5000,
            'method' => PaymentMethod::Cash,
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/payments/summary', [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.day.total', 5000)
            ->assertJsonPath('data.day.count', 1)
            ->assertJsonPath('data.week.total', 5000)
            ->assertJsonPath('data.month.total', 5000);
    }

    public function test_receptionist_cannot_view_payment_summary(): void
    {
        $response = $this->getJson('/api/v1/payments/summary', [
            'Authorization' => 'Bearer '.$this->receptionistToken,
        ]);

        $response->assertForbidden();
    }

    public function test_cannot_record_payment_for_foreign_salon_appointment(): void
    {
        $otherSalon = Salon::factory()->create();
        $foreignAppointment = Appointment::query()->create([
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
            'scheduled_at' => now(),
            'status' => AppointmentStatus::Completed,
        ]);

        $response = $this->postJson('/api/v1/payments', [
            'appointment_id' => $foreignAppointment->id,
            'amount' => 3000,
            'method' => PaymentMethod::Cash->value,
        ], ['Authorization' => 'Bearer '.$this->receptionistToken]);

        $response->assertBadRequest();
    }
}
