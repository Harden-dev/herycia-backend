<?php

namespace App\Http\Resources\V1\Salon;

use App\Data\Salon\SalonRegisterOutcome;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SalonRegisterOutcome */
class SalonRegisterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var SalonRegisterOutcome $outcome */
        $outcome = $this->resource;

        return [
            'access_token' => $outcome->accessToken,
            'token_type' => $outcome->tokenType,
            'expires_in' => $outcome->expiresIn,
            'expires_at' => $outcome->expiresAt,
            'salon' => new SalonResource($outcome->salon),
            'user' => [
                'id' => $outcome->user->id,
                'name' => $outcome->user->name,
                'phone' => $outcome->user->phone,
                'role' => $outcome->user->role?->value,
                'is_active' => $outcome->user->is_active,
            ],
            'subscription' => new SubscriptionResource($outcome->subscription),
        ];
    }
}
