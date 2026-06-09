<?php

namespace Tests\Feature\Salon;

use App\Enums\SalonStaffRole;
use App\Models\Plan;
use App\Models\Salon;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSalonSubscription;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class SalonServiceTest extends TestCase
{
    use CreatesSalonSubscription;
    use RefreshDatabase;

    private Salon $salon;

    private string $adminToken;

    private string $stylistToken;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::factory()->free()->create(['max_services' => 3]);
        $this->salon = Salon::factory()->create();
        $this->createActiveSubscription($this->salon, $plan);

        $admin = User::factory()->create([
            'salon_id' => $this->salon->id,
            'role' => SalonStaffRole::Admin,
        ]);
        $stylist = User::factory()->create([
            'salon_id' => $this->salon->id,
            'role' => SalonStaffRole::Stylist,
        ]);

        $this->adminToken = JWTAuth::fromUser($admin);
        $this->stylistToken = JWTAuth::fromUser($stylist);
    }

    private function adminHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->adminToken];
    }

    private function stylistHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->stylistToken];
    }

    public function test_staff_can_list_salon_services(): void
    {
        Service::query()->create([
            'salon_id' => $this->salon->id,
            'name' => 'Coupe homme',
            'duration_min' => 30,
            'price' => 2000,
            'is_active' => true,
        ]);

        $otherSalon = Salon::factory()->create();
        Service::query()->create([
            'salon_id' => $otherSalon->id,
            'name' => 'Autre',
            'duration_min' => 30,
            'price' => 1000,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/services', $this->stylistHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Coupe homme');
    }

    public function test_admin_can_create_service(): void
    {
        $response = $this->postJson('/api/v1/services', [
            'name' => 'Tresse',
            'duration_min' => 90,
            'price' => 8000,
        ], $this->adminHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Tresse')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('services', [
            'salon_id' => $this->salon->id,
            'name' => 'Tresse',
            'duration_min' => 90,
            'price' => 8000,
            'is_active' => true,
        ]);
    }

    public function test_stylist_cannot_create_service(): void
    {
        $response = $this->postJson('/api/v1/services', [
            'name' => 'Tresse',
            'duration_min' => 90,
            'price' => 8000,
        ], $this->stylistHeaders());

        $response->assertForbidden();
    }

    public function test_admin_can_update_service(): void
    {
        $service = Service::query()->create([
            'salon_id' => $this->salon->id,
            'name' => 'Old Name',
            'duration_min' => 30,
            'price' => 2000,
            'is_active' => true,
        ]);

        $response = $this->putJson("/api/v1/services/{$service->id}", [
            'name' => 'New Name',
            'price' => 2500,
        ], $this->adminHeaders());

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.price', 2500);
    }

    public function test_admin_can_deactivate_service(): void
    {
        $service = Service::query()->create([
            'salon_id' => $this->salon->id,
            'name' => 'Coupe',
            'duration_min' => 30,
            'price' => 2000,
            'is_active' => true,
        ]);

        $response = $this->deleteJson("/api/v1/services/{$service->id}", [], $this->adminHeaders());

        $response->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'is_active' => false,
        ]);
    }

    public function test_cannot_exceed_plan_service_limit(): void
    {
        Service::query()->create([
            'salon_id' => $this->salon->id,
            'name' => 'S1',
            'duration_min' => 30,
            'price' => 1000,
            'is_active' => true,
        ]);
        Service::query()->create([
            'salon_id' => $this->salon->id,
            'name' => 'S2',
            'duration_min' => 30,
            'price' => 1000,
            'is_active' => true,
        ]);
        Service::query()->create([
            'salon_id' => $this->salon->id,
            'name' => 'S3',
            'duration_min' => 30,
            'price' => 1000,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/services', [
            'name' => 'S4',
            'duration_min' => 30,
            'price' => 1000,
        ], $this->adminHeaders());

        $response->assertBadRequest();
    }

    public function test_cannot_access_service_from_another_salon(): void
    {
        $otherSalon = Salon::factory()->create();
        $service = Service::query()->create([
            'salon_id' => $otherSalon->id,
            'name' => 'Autre',
            'duration_min' => 30,
            'price' => 1000,
            'is_active' => true,
        ]);

        $response = $this->putJson("/api/v1/services/{$service->id}", [
            'name' => 'Hack',
        ], $this->adminHeaders());

        $response->assertNotFound();
    }
}
