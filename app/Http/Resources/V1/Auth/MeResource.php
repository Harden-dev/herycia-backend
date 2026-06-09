<?php

namespace App\Http\Resources\V1\Auth;

use App\Data\Auth\MeData;
use App\Http\Resources\V1\Salon\SalonResource;
use App\Http\Resources\V1\Salon\SubscriptionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MeData */
class MeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var MeData $me */
        $me = $this->resource;

        return [
            'user' => [
                'id' => $me->user->id,
                'name' => $me->user->name,
                'email' => $me->user->email,
                'phone' => $me->user->phone,
                'role' => $me->user->role?->value,
                'is_active' => $me->user->is_active,
                'created_at' => $me->user->created_at?->toIso8601String(),
            ],
            'salon' => $me->user->salon ? new SalonResource($me->user->salon) : null,
            'subscription' => $me->subscription ? new SubscriptionResource($me->subscription) : null,
        ];
    }
}
