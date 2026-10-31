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

    /** Lien du QR d'arrivée affiché au salon (la clé prouve la présence sur place). */
    public function buildCheckinLink(string $slug, string $checkinKey): string
    {
        return config('salono.frontend_url').'/arrivee/'.$slug.'?k='.urlencode($checkinKey);
    }

    /** Suivi public de la position dans la file. */
    public function buildQueueTrackingLink(string $queueToken): string
    {
        return config('salono.frontend_url').'/file/'.$queueToken;
    }
}
