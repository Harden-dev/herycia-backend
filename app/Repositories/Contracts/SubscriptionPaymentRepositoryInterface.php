<?php

namespace App\Repositories\Contracts;

use App\Models\SubscriptionPayment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SubscriptionPaymentRepositoryInterface
{
    public function create(array $data): SubscriptionPayment;

    public function findById(string $id): ?SubscriptionPayment;

    public function findByReference(string $reference): ?SubscriptionPayment;

    public function update(SubscriptionPayment $payment, array $data): SubscriptionPayment;

    /** Paiement encore payé le plus récemment activé de l'abonnement (created_at puis id). */
    public function findLatestPaidForSubscription(string $subscriptionId): ?SubscriptionPayment;

    public function existsOtherForSubscription(string $subscriptionId, string $exceptPaymentId): bool;

    public function paginateForAdmin(
        int $perPage = 15,
        ?string $from = null,
        ?string $to = null,
        ?string $planId = null,
        ?string $status = null,
        ?string $salonId = null,
    ): LengthAwarePaginator;
}
