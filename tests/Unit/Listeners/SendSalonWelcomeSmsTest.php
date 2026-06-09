<?php

namespace Tests\Unit\Listeners;

use App\Enums\SubscriptionStatus;
use App\Events\SalonRegistered;
use App\Listeners\SendSalonWelcomeSms;
use App\Models\Plan;
use App\Models\Salon;
use App\Models\Subscription;
use App\Models\User;
use App\Services\PublicLinkService;
use App\Services\Sms\TwilioSmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SendSalonWelcomeSmsTest extends TestCase
{
    use RefreshDatabase;
    public function test_sends_welcome_message_with_booking_link(): void
    {
        config(['salono.frontend_url' => 'https://salono.ci']);

        $plan = Plan::factory()->free()->create();
        $salon = Salon::factory()->create([
            'slug' => 'salon-koffi-cocody',
            'whatsapp_number' => '2250708112233',
        ]);
        $user = User::factory()->create([
            'salon_id' => $salon->id,
            'phone' => '2250708112233',
            'name' => 'Admin Koffi',
        ]);
        $subscription = Subscription::query()->create([
            'salon_id' => $salon->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Trial,
            'is_trial' => true,
        ]);

        $this->mock(PublicLinkService::class, function ($mock): void {
            $mock->shouldReceive('buildBookingLink')
                ->once()
                ->with('salon-koffi-cocody')
                ->andReturn('https://salono.ci/booking/salon-koffi-cocody');
        });

        $this->mock(TwilioSmsService::class, function ($mock): void {
            $mock->shouldReceive('send')
                ->once()
                ->withArgs(function (string $phone, string $body): bool {
                    return $phone === '+2250708112233'
                        && str_contains($body, 'https://salono.ci/booking/salon-koffi-cocody');
                });
        });

        app(SendSalonWelcomeSms::class)->handle(new SalonRegistered($salon, $user, $subscription));
    }

    public function test_skips_when_phone_invalid(): void
    {
        $plan = Plan::factory()->free()->create();
        $salon = Salon::factory()->create(['whatsapp_number' => 'invalid']);
        $user = User::factory()->create([
            'salon_id' => $salon->id,
            'phone' => 'invalid',
        ]);
        $subscription = Subscription::query()->create([
            'salon_id' => $salon->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Trial,
            'is_trial' => true,
        ]);

        $this->mock(TwilioSmsService::class, function ($mock): void {
            $mock->shouldNotReceive('send');
        });

        app(SendSalonWelcomeSms::class)->handle(new SalonRegistered($salon, $user, $subscription));
    }
}
