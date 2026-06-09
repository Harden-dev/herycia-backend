<?php

namespace App\Actions\Admin\Subscription;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Repositories\Contracts\SubscriptionRepositoryInterface;
use RuntimeException;

class CancelAdminSubscriptionAction
{
    public function __construct(
        private SubscriptionRepositoryInterface $subscriptionRepository,
    ) {}

    public function execute(string $id): Subscription
    {
        $subscription = $this->subscriptionRepository->findById($id);

        if (! $subscription) {
            throw new RuntimeException('Abonnement introuvable.');
        }

        $this->subscriptionRepository->update($subscription, [
            'status' => SubscriptionStatus::Cancelled,
        ]);

        return $subscription->refresh()->load(['salon', 'plan']);
    }
}
