<?php

namespace App\Actions\Admin\Subscription;

use App\Data\Admin\UpdateAdminSubscriptionData;
use App\Models\Subscription;
use App\Repositories\Contracts\SubscriptionRepositoryInterface;
use RuntimeException;

class UpdateAdminSubscriptionAction
{
    public function __construct(
        private SubscriptionRepositoryInterface $subscriptionRepository,
    ) {}

    public function execute(string $id, UpdateAdminSubscriptionData $data): Subscription
    {
        $subscription = $this->subscriptionRepository->findById($id);

        if (! $subscription) {
            throw new RuntimeException('Abonnement introuvable.');
        }

        $payload = $data->toArray();

        if ($payload === []) {
            throw new RuntimeException('Aucune donnée à mettre à jour.');
        }

        $this->subscriptionRepository->update($subscription, $payload);

        return $subscription->refresh()->load(['salon', 'plan']);
    }
}
