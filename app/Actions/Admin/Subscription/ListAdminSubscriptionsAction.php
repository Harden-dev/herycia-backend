<?php

namespace App\Actions\Admin\Subscription;

use App\Repositories\Contracts\SubscriptionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListAdminSubscriptionsAction
{
    public function __construct(
        private SubscriptionRepositoryInterface $subscriptionRepository,
    ) {}

    public function execute(
        int $perPage = 15,
        ?string $search = null,
        ?string $planId = null,
        ?string $status = null,
        ?string $salonId = null,
    ): LengthAwarePaginator {
        return $this->subscriptionRepository->paginateForAdmin(
            $perPage,
            $search,
            $planId,
            $status,
            $salonId,
        );
    }
}
