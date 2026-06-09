<?php

namespace Tests\Feature\Booking;

use App\Enums\AppointmentStatus;
use App\Jobs\Sms\SendAppointmentTrackingSms;
use App\Models\Appointment;
use App\Models\Client;
use App\Services\Booking\SlotAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesBookableSalon;
use Tests\TestCase;

class PublicBookingTest extends TestCase
{
    use CreatesBookableSalon;
    use RefreshDatabase;

    public function test_can_get_public_booking_info(): void
    {
        config(['salono.frontend_url' => 'https://salono.ci']);

        ['salon' => $salon, 'service' => $service] = $this->createBookableSalon();

        $response = $this->getJson('/api/booking/'.$salon->slug);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.salon.slug', $salon->slug)
            ->assertJsonPath(
                'data.salon.booking_link',
                'https://salono.ci/booking/salon-koffi-cocody',
            )
            ->assertJsonPath('data.services.0.id', $service->id)
            ->assertJsonCount(1, 'data.employees');
    }

    public function test_returns_salon_logo_url_when_available(): void
    {
        Storage::fake('public');

        ['salon' => $salon] = $this->createBookableSalon();
        $logoPath = 'logos/salon-logo.png';
        Storage::disk('public')->put($logoPath, 'png-content');
        $salon->update(['logo_url' => $logoPath]);

        $response = $this->getJson('/api/booking/'.$salon->slug);

        $response->assertOk()
            ->assertJsonPath('data.salon.logo_url', Storage::disk('public')->url($logoPath));
    }

    public function test_returns_404_for_unknown_slug(): void
    {
        $response = $this->getJson('/api/booking/unknown-salon');

        $response->assertNotFound()
            ->assertJsonPath('code', 'salon_not_found');
    }

    public function test_returns_403_when_booking_disabled(): void
    {
        ['salon' => $salon] = $this->createBookableSalon([], withOnlineBooking: false);

        $response = $this->getJson('/api/booking/'.$salon->slug);

        $response->assertForbidden()
            ->assertJsonPath('code', 'booking_disabled');
    }

    public function test_can_create_public_booking(): void
    {
        Queue::fake();

        ['salon' => $salon, 'stylist' => $stylist, 'service' => $service] = $this->createBookableSalon();

        $slots = app(SlotAvailabilityService::class)->getAvailableSlots($salon->id, $service);
        $this->assertNotEmpty($slots);

        $scheduledAt = $slots[0]->scheduledAt;

        $response = $this->postJson('/api/booking/'.$salon->slug, [
            'client_phone' => '2250708112233',
            'client_name' => 'Koffi Client',
            'service_id' => $service->id,
            'user_id' => $stylist->id,
            'date' => $scheduledAt->format('Y-m-d'),
            'time' => $scheduledAt->format('H:i'),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', AppointmentStatus::Confirmed->value)
            ->assertJsonPath('data.client.phone', '2250708112233')
            ->assertJsonStructure(['data' => ['tracking_token', 'tracking_link']]);

        $this->assertDatabaseHas('appointments', [
            'salon_id' => $salon->id,
            'user_id' => $stylist->id,
            'status' => AppointmentStatus::Confirmed->value,
        ]);

        $client = Client::query()->where('salon_id', $salon->id)->where('phone', '2250708112233')->first();
        $this->assertSame(0, $client->total_visits);

        Queue::assertPushed(SendAppointmentTrackingSms::class);
    }

    public function test_create_booking_requires_date_and_time(): void
    {
        ['salon' => $salon, 'stylist' => $stylist, 'service' => $service] = $this->createBookableSalon();

        $response = $this->postJson('/api/booking/'.$salon->slug, [
            'client_phone' => '2250708112233',
            'client_name' => 'Koffi Client',
            'service_id' => $service->id,
            'user_id' => $stylist->id,
            'date' => now()->addDay()->format('Y-m-d'),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['time']);
    }

    public function test_create_booking_accepts_legacy_scheduled_at(): void
    {
        Queue::fake();

        ['salon' => $salon, 'stylist' => $stylist, 'service' => $service] = $this->createBookableSalon();

        $slots = app(SlotAvailabilityService::class)->getAvailableSlots($salon->id, $service);
        $scheduledAt = $slots[0]->scheduledAt->toIso8601String();

        $response = $this->postJson('/api/booking/'.$salon->slug, [
            'client_phone' => '2250708334455',
            'client_name' => 'Legacy Client',
            'service_id' => $service->id,
            'user_id' => $stylist->id,
            'scheduled_at' => $scheduledAt,
        ]);

        $response->assertCreated();
    }

    public function test_returns_409_when_slot_is_taken(): void
    {
        Queue::fake();

        ['salon' => $salon, 'stylist' => $stylist, 'service' => $service] = $this->createBookableSalon();

        $slots = app(SlotAvailabilityService::class)->getAvailableSlots($salon->id, $service);
        $scheduledAt = $slots[0]->scheduledAt;

        Appointment::withoutEvents(fn () => Appointment::query()->create([
            'salon_id' => $salon->id,
            'client_id' => Client::factory()->create(['salon_id' => $salon->id])->id,
            'user_id' => $stylist->id,
            'service_id' => $service->id,
            'scheduled_at' => $scheduledAt,
            'status' => AppointmentStatus::Confirmed,
        ]));

        $response = $this->postJson('/api/booking/'.$salon->slug, [
            'client_phone' => '2250708223344',
            'client_name' => 'Autre Client',
            'service_id' => $service->id,
            'user_id' => $stylist->id,
            'date' => $scheduledAt->format('Y-m-d'),
            'time' => $scheduledAt->format('H:i'),
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('code', 'slot_unavailable');
    }
}
