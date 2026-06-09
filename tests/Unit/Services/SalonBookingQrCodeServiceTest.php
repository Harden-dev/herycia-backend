<?php

namespace Tests\Unit\Services;

use App\Services\PublicLinkService;
use App\Services\Salon\SalonBookingQrCodeService;
use Tests\TestCase;

class SalonBookingQrCodeServiceTest extends TestCase
{
    public function test_generates_png_data_uri_from_booking_link(): void
    {
        if (! extension_loaded('imagick')) {
            $this->markTestSkipped('Imagick extension required for PNG QR codes.');
        }

        config([
            'salono.frontend_url' => 'https://salono.ci',
            'salono.booking_qr_size' => 200,
        ]);

        $dataUri = app(SalonBookingQrCodeService::class)->generateDataUri('salon-koffi-cocody');

        $this->assertStringStartsWith('data:image/png;base64,', $dataUri);

        $png = base64_decode(substr($dataUri, strlen('data:image/png;base64,')));
        $this->assertNotFalse($png);
        $this->assertStringStartsWith("\x89PNG", $png);
    }

    public function test_png_encodes_booking_link(): void
    {
        if (! extension_loaded('imagick')) {
            $this->markTestSkipped('Imagick extension required for PNG QR codes.');
        }

        config(['salono.frontend_url' => 'https://salono.ci']);

        $link = app(PublicLinkService::class)->buildBookingLink('salon-koffi-cocody');
        $png = app(SalonBookingQrCodeService::class)->generatePng('salon-koffi-cocody');

        $this->assertStringStartsWith("\x89PNG", $png);
        $this->assertGreaterThan(100, strlen($png));
        $this->assertSame('https://salono.ci/booking/salon-koffi-cocody', $link);
    }
}
