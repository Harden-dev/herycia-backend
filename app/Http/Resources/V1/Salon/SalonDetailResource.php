<?php

namespace App\Http\Resources\V1\Salon;

use App\Data\Salon\SalonDetailData;
use App\Services\PublicLinkService;
use App\Services\Salon\SalonBookingQrCodeService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SalonDetailData */
class SalonDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var SalonDetailData $detail */
        $detail = $this->resource;
        $salon = $detail->salon;

        return [
            'id' => $salon->id,
            'name' => $salon->name,
            'slug' => $salon->slug,
            'phone' => $salon->phone,
            'whatsapp_number' => $salon->whatsapp_number,
            'city' => $salon->city,
            'address' => $salon->address,
            'late_tolerance_minutes' => $salon->late_tolerance_minutes ?? 15,
            'logo_url' => $detail->logoUrl,
            'is_active' => $salon->is_active,
            'booking_link' => app(PublicLinkService::class)->buildBookingLink($salon->slug),
            'booking_qr_code' => app(SalonBookingQrCodeService::class)->generateDataUri($salon->slug),
            
            'created_at' => $salon->created_at?->toIso8601String(),
            'subscription' => $detail->subscription
                ? new SubscriptionResource($detail->subscription)
                : null,
        ];
    }
}
