<?php

namespace App\Http\Resources\V1\Salon;

use App\Services\PublicLinkService;
use App\Services\Salon\SalonBookingQrCodeService;
use App\Services\Storage\SalonLogoStorageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Salon */
class SalonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'whatsapp_number' => $this->whatsapp_number,
            'city' => $this->city,
            'is_active' => $this->is_active,
            'logo_url' => app(SalonLogoStorageService::class)->url($this->logo_url),
            'booking_link' => app(PublicLinkService::class)->buildBookingLink($this->slug),
            'booking_qr_code' => app(SalonBookingQrCodeService::class)->generateDataUri($this->slug),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
