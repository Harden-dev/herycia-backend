<?php

namespace Tests\Feature\Booking;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesBookableSalon;
use Tests\TestCase;

class AppointmentTrackingTest extends TestCase
{
    use CreatesBookableSalon;
    use RefreshDatabase;

    public function test_can_get_appointment_by_tracking_token(): void
    {
        config(['salono.frontend_url' => 'https://salono.ci']);

        ['salon' => $salon, 'stylist' => $stylist, 'service' => $service] = $this->createBookableSalon();
        $client = Client::factory()->create(['salon_id' => $salon->id]);

        $appointment = Appointment::withoutEvents(fn () => Appointment::query()->create([
            'salon_id' => $salon->id,
            'client_id' => $client->id,
            'user_id' => $stylist->id,
            'service_id' => $service->id,
            'scheduled_at' => now()->addDay(),
            'status' => AppointmentStatus::Confirmed,
            'tracking_token' => 'abc123token',
        ]));

        $response = $this->getJson('/api/rdv/'.$appointment->tracking_token);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tracking_token', 'abc123token')
            ->assertJsonPath('data.tracking_link', 'https://salono.ci/rdv/abc123token')
            ->assertJsonPath('data.status', AppointmentStatus::Confirmed->value)
            ->assertJsonPath('data.salon.name', $salon->name)
            ->assertJsonPath('data.service.name', $service->name);
    }

    public function test_returns_salon_logo_url_when_available(): void
    {
        Storage::fake('public');

        ['salon' => $salon, 'stylist' => $stylist, 'service' => $service] = $this->createBookableSalon();
        $logoPath = 'logos/salon-logo.png';
        Storage::disk('public')->put($logoPath, 'png-content');
        $salon->update(['logo_url' => $logoPath]);

        $client = Client::factory()->create(['salon_id' => $salon->id]);

        $appointment = Appointment::withoutEvents(fn () => Appointment::query()->create([
            'salon_id' => $salon->id,
            'client_id' => $client->id,
            'user_id' => $stylist->id,
            'service_id' => $service->id,
            'scheduled_at' => now()->addDay(),
            'status' => AppointmentStatus::Confirmed,
            'tracking_token' => 'logo123token',
        ]));

        $response = $this->getJson('/api/rdv/'.$appointment->tracking_token);

        $response->assertOk()
            ->assertJsonPath('data.salon.logo_url', Storage::disk('public')->url($logoPath));
    }

    public function test_returns_404_for_unknown_tracking_token(): void
    {
        $response = $this->getJson('/api/rdv/unknown-token');

        $response->assertNotFound()
            ->assertJsonPath('code', 'appointment_not_found');
    }
}
