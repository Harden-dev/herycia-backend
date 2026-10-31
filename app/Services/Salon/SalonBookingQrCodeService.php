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
        // Le PNG exige l'extension imagick ; sans elle, repli en SVG (affichable en <img>)
        // pour ne pas faire échouer la connexion ni /auth/me (audit A8).
        if (! extension_loaded('imagick')) {
            return 'data:image/svg+xml;base64,'.base64_encode($this->generateSvg($slug));
        }

        return 'data:image/png;base64,'.base64_encode($this->generatePng($slug));
    }

    public function generateSvg(string $slug): string
    {
        return (string) QrCode::format('svg')
            ->size((int) config('salono.booking_qr_size', 300))
            ->margin(2)
            ->errorCorrection('M')
            ->generate($this->publicLinkService->buildBookingLink($slug));
    }

    /** QR (data URI) pour une URL quelconque, par exemple le QR d'arrivée. PNG si imagick, sinon SVG. */
    public function dataUriForUrl(string $url): string
    {
        $size = (int) config('salono.booking_qr_size', 300);

        if (! extension_loaded('imagick')) {
            $svg = (string) QrCode::format('svg')->size($size)->margin(2)->errorCorrection('M')->generate($url);

            return 'data:image/svg+xml;base64,'.base64_encode($svg);
        }

        $png = (string) QrCode::format('png')->size($size)->margin(2)->errorCorrection('M')->generate($url);

        return 'data:image/png;base64,'.base64_encode($png);
    }
}
