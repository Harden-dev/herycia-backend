<?php

namespace App\Actions\Admin\Subscription;

use App\Models\Subscription;
use App\Repositories\Contracts\SubscriptionRepositoryInterface;
use RuntimeException;

class GetAdminSubscriptionAction
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

        return $subscription->load(['salon', 'plan']);
    }
}
