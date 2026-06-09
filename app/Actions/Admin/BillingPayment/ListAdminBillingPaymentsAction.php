<?php

namespace App\Actions\Admin\BillingPayment;

use App\Repositories\Contracts\SubscriptionPaymentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListAdminBillingPaymentsAction
{
    public function __construct(
        private SubscriptionPaymentRepositoryInterface $subscriptionPaymentRepository,
    ) {}

    public function execute(
        int $perPage = 15,
        ?string $from = null,
        ?string $to = null,
        ?string $planId = null,
        ?string $status = null,
        ?string $salonId = null,
    ): LengthAwarePaginator {
        return $this->subscriptionPaymentRepository->paginateForAdmin(
            $perPage,
            $from,
            $to,
            $planId,
            $status,
            $salonId,
        );
    }
}
