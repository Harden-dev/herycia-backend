<?php

namespace Tests\Feature\Salon;

use App\Enums\SalonStaffRole;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Salon;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class SalonEmployeeTest extends TestCase
{
    use RefreshDatabase;

    private Salon $salon;

    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::factory()->free()->create(['max_employees' => 3]);
        $this->salon = Salon::factory()->create();
        Subscription::query()->create([
            'salon_id' => $this->salon->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Trial,
            'is_trial' => true,
            'started_at' => null,
            'ends_at' => null,
            'trial_ends_at' => null,
        ]);

        $admin = User::factory()->create([
            'salon_id' => $this->salon->id,
            'role' => SalonStaffRole::Admin,
        ]);
        $this->adminToken = JWTAuth::fromUser($admin);
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->adminToken];
    }

    public function test_admin_can_list_salon_employees(): void
    {
        User::factory()->create([
            'salon_id' => $this->salon->id,
            'role' => SalonStaffRole::Stylist,
            'name' => 'Styliste 1',
        ]);

        $otherSalon = Salon::factory()->create();
        User::factory()->create(['salon_id' => $otherSalon->id, 'role' => SalonStaffRole::Stylist]);

        $response = $this->getJson('/api/v1/users', $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_can_create_employee(): void
    {
        $response = $this->postJson('/api/v1/users', [
            'name' => 'Aya Koné',
            'phone' => '0748754918',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => SalonStaffRole::Stylist->value,
        ], $this->authHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Aya Koné')
            ->assertJsonPath('data.role', SalonStaffRole::Stylist->value);

        $this->assertDatabaseHas('users', [
            'salon_id' => $this->salon->id,
            'phone' => '2250748754918',
            'role' => SalonStaffRole::Stylist->value,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_update_employee(): void
    {
        $employee = User::factory()->create([
            'salon_id' => $this->salon->id,
            'role' => SalonStaffRole::Receptionist,
            'name' => 'Old Name',
        ]);

        $response = $this->putJson("/api/v1/users/{$employee->id}", [
            'name' => 'New Name',
            'role' => SalonStaffRole::Manager->value,
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.role', SalonStaffRole::Manager->value);
    }

    public function test_admin_can_deactivate_employee(): void
    {
        $employee = User::factory()->create([
            'salon_id' => $this->salon->id,
            'role' => SalonStaffRole::Stylist,
            'is_active' => true,
        ]);

        $response = $this->deleteJson("/api/v1/users/{$employee->id}", [], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
            'is_active' => false,
        ]);
    }

    public function test_admin_can_reactivate_employee(): void
    {
        $employee = User::factory()->create([
            'salon_id' => $this->salon->id,
            'role' => SalonStaffRole::Stylist,
            'is_active' => false,
        ]);

        $response = $this->deleteJson("/api/v1/users/{$employee->id}", [], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
            'is_active' => true,
        ]);
    }

    public function test_admin_cannot_deactivate_self(): void
    {
        $admin = User::query()->where('salon_id', $this->salon->id)
            ->where('role', SalonStaffRole::Admin)->first();

        $response = $this->deleteJson("/api/v1/users/{$admin->id}", [], $this->authHeaders());

        $response->assertStatus(400);
    }

    public function test_cannot_access_employee_from_another_salon(): void
    {
        $otherSalon = Salon::factory()->create();
        $employee = User::factory()->create([
            'salon_id' => $otherSalon->id,
            'role' => SalonStaffRole::Stylist,
        ]);

        $response = $this->getJson("/api/v1/users/{$employee->id}", $this->authHeaders());

        $response->assertNotFound();
    }

    public function test_cannot_create_admin_employee(): void
    {
        $response = $this->postJson('/api/v1/users', [
            'name' => 'Fake Admin',
            'phone' => '2250708223344',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => SalonStaffRole::Admin->value,
        ], $this->authHeaders());

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    }
}
