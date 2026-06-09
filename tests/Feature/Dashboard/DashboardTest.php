<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\QueueEntryStatus;
use App\Enums\SalonStaffRole;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Payment;
use App\Models\QueueEntry;
use App\Models\Plan;
use App\Models\Salon;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSalonSubscription;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class DashboardTest extends TestCase
{
    use CreatesSalonSubscription;
    use RefreshDatabase;

    private Salon $salon;

    private User $stylist;

    private string $token;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salon = Salon::factory()->create();
        $this->createActiveSubscription($this->salon, Plan::factory()->premium()->create());

        $this->stylist = User::factory()->create([
            'salon_id' => $this->salon->id,
            'role' => SalonStaffRole::Stylist,
        ]);

        $this->token = JWTAuth::fromUser($this->stylist);

        $this->service = Service::query()->create([
            'salon_id' => $this->salon->id,
            'name' => 'Coupe homme',
            'duration_min' => 30,
            'price' => 8500,
            'is_active' => true,
        ]);
    }

    public function test_overview_returns_kpi_metrics(): void
    {
        $client = Client::factory()->create([
            'salon_id' => $this->salon->id,
            'last_visit_at' => now()->subDays(5),
        ]);

        $appointment = Appointment::query()->create([
            'salon_id' => $this->salon->id,
            'client_id' => $client->id,
            'user_id' => $this->stylist->id,
            'service_id' => $this->service->id,
            'scheduled_at' => now(),
            'status' => AppointmentStatus::Confirmed,
        ]);

        Payment::query()->create([
            'salon_id' => $this->salon->id,
            'appointment_id' => $appointment->id,
            'client_id' => $client->id,
            'amount' => 8500,
            'method' => PaymentMethod::MobileMoney,
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);

        QueueEntry::query()->create([
            'salon_id' => $this->salon->id,
            'client_id' => $client->id,
            'user_id' => $this->stylist->id,
            'service_id' => $this->service->id,
            'position' => 1,
            'status' => QueueEntryStatus::Waiting,
            'arrived_at' => now()->subMinutes(12),
        ]);

        $response = $this->getJson('/api/v1/dashboard/overview', [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.appointments_today.value', 1)
            ->assertJsonPath('data.revenue_today.value', 8500)
            ->assertJsonPath('data.active_clients.value', 1)
            ->assertJsonPath('data.queue_waiting.value', 1);
    }

    public function test_clients_stats_returns_monthly_points(): void
    {
        Client::factory()->create([
            'salon_id' => $this->salon->id,
            'created_at' => now()->startOfMonth(),
        ]);

        $response = $this->getJson('/api/v1/dashboard/clients-stats?period=month', [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.period', 'month')
            ->assertJsonCount(12, 'data.points');
    }

    public function test_revenue_stats_requires_period(): void
    {
        $response = $this->getJson('/api/v1/dashboard/revenue-stats', [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertUnprocessable();
    }

    public function test_revenue_stats_returns_weekly_points(): void
    {
        $client = Client::factory()->create(['salon_id' => $this->salon->id]);

        $appointment = Appointment::query()->create([
            'salon_id' => $this->salon->id,
            'client_id' => $client->id,
            'user_id' => $this->stylist->id,
            'service_id' => $this->service->id,
            'scheduled_at' => now(),
            'status' => AppointmentStatus::Completed,
        ]);

        Payment::query()->create([
            'salon_id' => $this->salon->id,
            'appointment_id' => $appointment->id,
            'client_id' => $client->id,
            'amount' => 15000,
            'method' => PaymentMethod::Cash,
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/dashboard/revenue-stats?period=week', [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.period', 'week')
            ->assertJsonStructure([
                'data' => [
                    'points' => [['label', 'value']],
                    'total',
                    'trend_percent',
                ],
            ]);
    }

    public function test_activity_returns_recent_events(): void
    {
        $client = Client::factory()->create([
            'salon_id' => $this->salon->id,
            'name' => 'Koffi Atta',
            'created_at' => now()->subHour(),
        ]);

        Appointment::query()->create([
            'salon_id' => $this->salon->id,
            'client_id' => $client->id,
            'user_id' => $this->stylist->id,
            'service_id' => $this->service->id,
            'scheduled_at' => now()->setTime(10, 0),
            'status' => AppointmentStatus::Confirmed,
            'created_at' => now()->subMinutes(5),
        ]);

        $response = $this->getJson('/api/v1/dashboard/activity?limit=5', [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    ['id', 'type', 'message', 'created_at'],
                ],
            ]);
    }

    public function test_queue_returns_waiting_entries(): void
    {
        $client = Client::factory()->create([
            'salon_id' => $this->salon->id,
            'name' => 'Koffi Atta',
        ]);

        QueueEntry::query()->create([
            'salon_id' => $this->salon->id,
            'client_id' => $client->id,
            'user_id' => $this->stylist->id,
            'service_id' => $this->service->id,
            'position' => 1,
            'status' => QueueEntryStatus::Waiting,
            'arrived_at' => now()->subMinutes(12),
        ]);

        $response = $this->getJson('/api/v1/queue?limit=5', [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.client_name', 'Koffi Atta')
            ->assertJsonPath('data.0.service_name', 'Coupe homme')
            ->assertJsonPath('data.0.status', 'waiting');
    }

    public function test_dashboard_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/dashboard/overview')->assertUnauthorized();
        $this->getJson('/api/v1/queue')->assertUnauthorized();
    }
}
