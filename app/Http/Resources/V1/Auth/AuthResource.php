<?php

namespace App\Http\Resources\V1\Auth;

use App\Http\Resources\V1\Salon\SubscriptionResource;
use App\Repositories\Contracts\SubscriptionRepositoryInterface;
use App\Services\PublicLinkService;
use App\Services\Salon\SalonBookingQrCodeService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->resource['user'];
        $subscription = $user->salon_id !== null
            ? app(SubscriptionRepositoryInterface::class)->findCurrentBySalonId($user->salon_id)
            : null;

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role?->value,
                'is_active' => $user->is_active,
                'salon' => $user->salon ? [
                    'id' => $user->salon->id,
                    'name' => $user->salon->name,
                    'slug' => $user->salon->slug,
                    'logo_url' => $user->salon->logo_url,
                    'booking_link' => app(PublicLinkService::class)->buildBookingLink($user->salon->slug),
                    'booking_qr_code' => app(SalonBookingQrCodeService::class)->generateDataUri($user->salon->slug),
                ] : null,

                'subscription' => $subscription !== null
                    ? new SubscriptionResource($subscription->loadMissing('plan'))
                    : null,
            ],
            'access_token' => $this->resource['token'],
            'token_type' => $this->resource['token_type'],
            'expires_in' => $this->resource['expires_in'],
            'expires_at' => $this->resource['expires_at'],
        ];
    }
}
