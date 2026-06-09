<?php

namespace Tests\Unit\Services;

use App\Services\PublicLinkService;
use Tests\TestCase;

class PublicLinkServiceTest extends TestCase
{
    public function test_builds_booking_link(): void
    {
        config(['salono.frontend_url' => 'https://salono.ci']);

        $link = app(PublicLinkService::class)->buildBookingLink('salon-koffi-cocody');

        $this->assertSame('https://salono.ci/booking/salon-koffi-cocody', $link);
    }

    public function test_builds_tracking_link(): void
    {
        config(['salono.frontend_url' => 'https://salono.ci']);

        $link = app(PublicLinkService::class)->buildTrackingLink('abc123token');

        $this->assertSame('https://salono.ci/rdv/abc123token', $link);
    }
}
