<?php

namespace Tests\Feature\Salon;

use App\Enums\SalonStaffRole;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Salon;
use App\Models\User;
use App\Services\Salon\SalonBookingQrCodeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SalonRegisterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(SalonBookingQrCodeService::class, function ($mock): void {
            $mock->shouldReceive('generateDataUri')
                ->andReturn('data:image/png;base64,iVBORw0KGgo=');
        });
    }

    private function seedBasicPlan(): void
    {
        Plan::factory()->basic()->create();
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'salon_name' => 'Salon Koffi Cocody',
            'city' => 'Abidjan',
            'whatsapp_number' => '2250708112233',
            'admin_name' => 'Koffi Atta',
            'phone' => '2250708112233',
            'password' => 'monmotdepasse8',
            'password_confirmation' => 'monmotdepasse8',
            'plan_code' => 'basic',
        ], $overrides);
    }

    public function test_registers_salon_owner_and_trial_subscription_for_selected_plan(): void
    {
        Event::fake();
        $this->seedBasicPlan();
        Carbon::setTestNow('2026-06-05 10:00:00');

        $response = $this->postJson('/api/v1/auth/register', $this->validPayload());

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Inscription réussie.')
            ->assertJsonPath('data.user.role', SalonStaffRole::Admin->value)
            ->assertJsonPath('data.subscription.status', SubscriptionStatus::Trial->value)
            ->assertJsonPath('data.subscription.plan.code', 'basic')
            ->assertJsonPath('data.subscription.plan.price_fcfa', 5000)
            ->assertJsonPath('data.subscription.trial_ends_at', '2026-06-12')
            ->assertJsonPath('data.salon.slug', 'salon-koffi-cocody')
            ->assertJsonStructure([
                'data' => [
                    'access_token',
                    'token_type',
                    'salon' => ['id', 'name', 'booking_link', 'booking_qr_code'],
                    'user' => ['id', 'name', 'phone'],
                    'subscription' => ['id', 'status', 'plan'],
                ],
            ]);

        $this->assertDatabaseHas('salons', [
            'name' => 'Salon Koffi Cocody',
            'whatsapp_number' => '2250708112233',
            'city' => 'Abidjan',
        ]);

        $this->assertDatabaseHas('users', [
            'name' => 'Koffi Atta',
            'phone' => '2250708112233',
            'role' => SalonStaffRole::Admin->value,
        ]);

        Event::assertDispatched(\App\Events\SalonRegistered::class);
    }

    public function test_registers_salon_with_pro_plan(): void
    {
        Event::fake();
        Plan::factory()->pro()->create();

        $response = $this->postJson('/api/v1/auth/register', $this->validPayload([
            'plan_code' => 'pro',
            'phone' => '2250708223344',
            'whatsapp_number' => '2250708223344',
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.subscription.plan.code', 'pro')
            ->assertJsonPath('data.subscription.plan.max_employees', null);
    }

    public function test_registers_salon_with_local_phone_format(): void
    {
        Event::fake();
        $this->seedBasicPlan();

        $response = $this->postJson('/api/v1/auth/register', $this->validPayload([
            'phone' => '0748754918',
            'whatsapp_number' => '+2250748754918',
        ]));

        $response->assertCreated();

        $this->assertDatabaseHas('users', ['phone' => '2250748754918']);
        $this->assertDatabaseHas('salons', ['whatsapp_number' => '2250748754918']);
    }

    public function test_returns_validation_error_for_invalid_plan_code(): void
    {
        $this->seedBasicPlan();

        $response = $this->postJson('/api/v1/auth/register', $this->validPayload([
            'plan_code' => 'enterprise',
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['plan_code']);
    }

    public function test_returns_validation_error_for_invalid_phone(): void
    {
        $this->seedBasicPlan();

        $response = $this->postJson('/api/v1/auth/register', $this->validPayload([
            'phone' => '08112233',
        ]));

        $response->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_returns_validation_error_when_phone_already_used(): void
    {
        $this->seedBasicPlan();

        $salon = Salon::factory()->create();
        User::factory()->create([
            'salon_id' => $salon->id,
            'phone' => '2250708112233',
        ]);

        $response = $this->postJson('/api/v1/auth/register', $this->validPayload([
            'phone' => '2250708112233',
            'whatsapp_number' => '2250708223344',
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_returns_validation_error_when_whatsapp_already_used(): void
    {
        $this->seedBasicPlan();

        Salon::factory()->create(['whatsapp_number' => '2250708112233']);

        $response = $this->postJson('/api/v1/auth/register', $this->validPayload([
            'phone' => '2250708223344',
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['whatsapp_number']);
    }

    public function test_returns_server_error_when_selected_plan_missing(): void
    {
        $response = $this->postJson('/api/v1/auth/register', $this->validPayload([
            'phone' => '2250708223344',
            'whatsapp_number' => '2250708223344',
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['plan_code']);

        $this->assertDatabaseCount('salons', 0);
        $this->assertDatabaseCount('users', 0);
    }
}
