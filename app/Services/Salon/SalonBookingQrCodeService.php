<?php

namespace App\Services\Salon;

use App\Services\PublicLinkService;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class SalonBookingQrCodeService
{
    public function __construct(
        private PublicLinkService $publicLinkService,
    ) {}

    public function generatePng(string $slug): string
    {
        $bookingLink = $this->publicLinkService->buildBookingLink($slug);

        return QrCode::format('png')
            ->size((int) config('salono.booking_qr_size', 300))
            ->margin(2)
            ->errorCorrection('M')
            ->generate($bookingLink);
    }

    public function generateDataUri(string $slug): string
    {
        return 'data:image/png;base64,'.base64_encode($this->generatePng($slug));
    }
}
