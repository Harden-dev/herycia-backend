<?php

namespace App\Http\Resources\Booking;

use App\Data\Booking\PublicSalonBookingData;
use App\Services\Storage\SalonLogoStorageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PublicSalonBookingData */
class PublicSalonBookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var PublicSalonBookingData $data */
        $data = $this->resource;
        $salon = $data->salon;

        return [
            'salon' => [
                'id' => $salon->id,
                'name' => $salon->name,
                'slug' => $salon->slug,
                'city' => $salon->city,
                'logo_url' => app(SalonLogoStorageService::class)->url($salon->logo_url),
                'whatsapp_number' => $salon->whatsapp_number,
                'booking_link' => $data->bookingLink,
            ],
            'services' => $data->services->map(fn ($service) => [
                'id' => $service->id,
                'name' => $service->name,
                'duration_min' => $service->duration_min,
                'price' => $service->price,
            ])->values(),
            'employees' => $data->employees->map(fn ($employee) => [
                'id' => $employee->id,
                'name' => $employee->name,
            ])->values(),
        ];
    }
}
