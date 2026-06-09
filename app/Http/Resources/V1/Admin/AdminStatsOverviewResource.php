<?php

namespace App\Http\Resources\V1\Admin;

use App\Data\Admin\AdminStatsOverviewData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AdminStatsOverviewData */
class AdminStatsOverviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var AdminStatsOverviewData $overview */
        $overview = $this->resource;

        return [
            'salons_count' => $overview->salonsCount,
            'users_count' => $overview->usersCount,
            'mrr' => $overview->mrr,
            'new_salons_this_month' => $overview->newSalonsThisMonth,
            'expired_subscriptions_count' => $overview->expiredSubscriptionsCount,
            'appointments_count' => $overview->appointmentsCount,
            'recent_payments' => AdminBillingPaymentResource::collection($overview->recentBillingPayments),
        ];
    }
}
