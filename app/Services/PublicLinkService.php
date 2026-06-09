<?php

namespace App\Services;

class PublicLinkService
{
    public function buildBookingLink(string $slug): string
    {
        return config('salono.frontend_url').'/booking/'.$slug;
    }

    public function buildTrackingLink(string $trackingToken): string
    {
        return config('salono.frontend_url').'/rdv/'.$trackingToken;
    }
}
