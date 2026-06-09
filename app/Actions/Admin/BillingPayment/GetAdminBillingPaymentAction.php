<?php

namespace App\Actions\Admin\BillingPayment;

use App\Models\SubscriptionPayment;
use App\Repositories\Contracts\SubscriptionPaymentRepositoryInterface;
use RuntimeException;

class GetAdminBillingPaymentAction
{
    public function __construct(
        private SubscriptionPaymentRepositoryInterface $subscriptionPaymentRepository,
    ) {}

    public function execute(string $id): SubscriptionPayment
    {
        $billingPayment = $this->subscriptionPaymentRepository->findById($id);

        if (! $billingPayment) {
            throw new RuntimeException('Paiement introuvable.');
        }

        return $billingPayment->load(['salon', 'plan', 'subscription']);
    }
}
