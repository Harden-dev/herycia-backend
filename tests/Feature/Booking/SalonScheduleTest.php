<?php

namespace Tests\Feature\Booking;

use App\Models\Salon;
use App\Services\Booking\SlotAvailabilityService;
use App\Services\Salon\SalonDefaultScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBookableSalon;
use Tests\TestCase;

class SalonScheduleTest extends TestCase
{
    use CreatesBookableSalon;
    use RefreshDatabase;

    public function test_default_schedule_seeds_seven_days(): void
    {
        $salon = Salon::factory()->create();

        app(SalonDefaultScheduleService::class)->seedForSalon($salon->id);

        $this->assertDatabaseCount('salon_schedules', 7);
        $this->assertDatabaseHas('salon_schedules', [
            'salon_id' => $salon->id,
            'day_of_week' => 0,
            'is_closed' => true,
        ]);
        $this->assertDatabaseHas('salon_schedules', [
            'salon_id' => $salon->id,
            'day_of_week' => 1,
            'opens_at' => '08:00:00',
            'closes_at' => '20:00:00',
            'is_closed' => false,
        ]);
    }

    public function test_slot_availability_respects_schedule_and_returns_max_five(): void
    {
        ['salon' => $salon, 'service' => $service] = $this->createBookableSalon();

        $slots = app(SlotAvailabilityService::class)->getAvailableSlots($salon->id, $service);

        $this->assertNotEmpty($slots);
        $this->assertLessThanOrEqual(5, count($slots));

        foreach ($slots as $slot) {
            $this->assertTrue($slot->scheduledAt->isFuture());
        }
    }
}
