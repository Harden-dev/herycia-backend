<?php

namespace App\Actions\Admin;

use App\Models\SubscriptionPayment;
use App\Repositories\Contracts\SubscriptionPaymentRepositoryInterface;

class AdminBillingPaymentAction
{
    public function __construct(
        private SubscriptionPaymentRepositoryInterface $subscriptionPaymentRepository,
    ) {}

    public function index(
        int $perPage = 15,
        ?string $from = null,
        ?string $to = null,
        ?string $planId = null,
        ?string $status = null,
        ?string $salonId = null,
    ): \Illuminate\Contracts\Pagination\LengthAwarePaginator {
        return $this->subscriptionPaymentRepository->paginateForAdmin(
            $perPage,
            $from,
            $to,
            $planId,
            $status,
            $salonId,
        );
    }

    public function show(string $paymentId): SubscriptionPayment
    {
        $payment = $this->subscriptionPaymentRepository->findById($paymentId);

        if (! $payment) {
            throw new \RuntimeException('Paiement de facturation introuvable.');
        }

        return $payment;
    }
}
