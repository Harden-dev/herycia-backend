<?php

namespace Tests\Feature\Salon;

use App\Enums\SalonStaffRole;
use App\Models\Plan;
use App\Models\Salon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalonLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_accepts_local_phone_format(): void
    {
        $salon = Salon::factory()->create();
        User::factory()->create([
            'salon_id' => $salon->id,
            'phone' => '2250748754918',
            'password' => 'monmotdepasse8',
            'role' => SalonStaffRole::Admin,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => '0748754918',
            'password' => 'monmotdepasse8',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['access_token']]);
    }

    public function test_login_accepts_international_phone_with_plus(): void
    {
        $salon = Salon::factory()->create();
        User::factory()->create([
            'salon_id' => $salon->id,
            'phone' => '2250748754918',
            'password' => 'monmotdepasse8',
            'role' => SalonStaffRole::Admin,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => '+2250748754918',
            'password' => 'monmotdepasse8',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);
    }
}
