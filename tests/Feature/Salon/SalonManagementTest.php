<?php

namespace Tests\Feature\Salon;

use App\Enums\SalonStaffRole;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Salon;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Salon\SalonBookingQrCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class SalonManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        $this->mock(SalonBookingQrCodeService::class, function ($mock) use ($pngBytes): void {
            $mock->shouldReceive('generateDataUri')
                ->andReturn('data:image/png;base64,iVBORw0KGgo=');
            $mock->shouldReceive('generatePng')
                ->andReturn($pngBytes);
        });
    }

    private function actingAsAdmin(Salon $salon): string
    {
        $user = User::factory()->create([
            'salon_id' => $salon->id,
            'role' => SalonStaffRole::Admin,
        ]);

        return JWTAuth::fromUser($user);
    }

    private function seedSubscription(Salon $salon): void
    {
        $plan = Plan::factory()->free()->create();
        Subscription::query()->create([
            'salon_id' => $salon->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Trial,
            'is_trial' => true,
            'started_at' => null,
            'ends_at' => null,
            'trial_ends_at' => null,
        ]);
    }

    public function test_admin_can_get_salon_details(): void
    {
        Storage::fake('public');

        $salon = Salon::factory()->create(['name' => 'Mon Salon']);
        $this->seedSubscription($salon);
        $token = $this->actingAsAdmin($salon);

        $response = $this->getJson('/api/v1/salon', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Mon Salon')
            ->assertJsonPath('data.subscription.status', SubscriptionStatus::Trial->value)
            ->assertJsonPath('data.booking_qr_code', 'data:image/png;base64,iVBORw0KGgo=');
    }

    public function test_admin_can_download_booking_qr_png(): void
    {
        $salon = Salon::factory()->create(['slug' => 'salon-test-qr']);
        $this->seedSubscription($salon);
        $token = $this->actingAsAdmin($salon);

        $response = $this->get('/api/v1/salon/booking-qr', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('Content-Disposition', 'inline; filename="booking-qr-salon-test-qr.png"');
    }

    public function test_non_admin_cannot_access_salon(): void
    {
        $salon = Salon::factory()->create();
        $user = User::factory()->create([
            'salon_id' => $salon->id,
            'role' => SalonStaffRole::Stylist,
        ]);
        $token = JWTAuth::fromUser($user);

        $response = $this->getJson('/api/v1/salon', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertForbidden();
    }

    public function test_admin_can_update_salon_profile(): void
    {
        Storage::fake('public');

        $salon = Salon::factory()->create();
        $this->seedSubscription($salon);
        $token = $this->actingAsAdmin($salon);

        $response = $this->putJson('/api/v1/salon', [
            'name' => 'Salon Renommé',
            'address' => 'Plateau, Abidjan',
            'phone' => '0748754918',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Salon Renommé')
            ->assertJsonPath('data.address', 'Plateau, Abidjan')
            ->assertJsonPath('data.phone', '2250748754918');

        $this->assertDatabaseHas('salons', [
            'id' => $salon->id,
            'name' => 'Salon Renommé',
            'phone' => '2250748754918',
        ]);
    }

    public function test_admin_can_upload_salon_logo(): void
    {
        Storage::fake('public');

        $salon = Salon::factory()->create();
        $this->seedSubscription($salon);
        $token = $this->actingAsAdmin($salon);

        $response = $this->postJson('/api/v1/salon/logo', [
            'logo' => UploadedFile::fake()->image('logo.jpg'),
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $salon->refresh();
        $this->assertNotNull($salon->logo_url);
        Storage::disk('public')->assertExists($salon->logo_url);
    }
}
