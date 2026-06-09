<?php

namespace App\Http\Resources\Booking;

use App\Services\PublicLinkService;
use App\Services\Storage\SalonLogoStorageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Appointment */
class PublicAppointmentTrackingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tracking_token' => $this->tracking_token,
            'tracking_link' => $this->tracking_token !== null
                ? app(PublicLinkService::class)->buildTrackingLink($this->tracking_token)
                : null,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'status' => $this->status?->value,
            'notes' => $this->notes,
            'salon' => $this->whenLoaded('salon', fn () => [
                'name' => $this->salon?->name,
                'city' => $this->salon?->city,
                'address' => $this->salon?->address,
                'phone' => $this->salon?->phone,
                'logo_url' => app(SalonLogoStorageService::class)->url($this->salon?->logo_url),
            ]),
            'service' => $this->whenLoaded('service', fn () => [
                'name' => $this->service?->name,
                'duration_min' => $this->service?->duration_min,
                'price' => $this->service?->price,
            ]),
            'employee' => $this->whenLoaded('staff', fn () => [
                'name' => $this->staff?->name,
            ]),
            'client' => $this->whenLoaded('client', fn () => [
                'name' => $this->client?->name,
            ]),
        ];
    }
}
