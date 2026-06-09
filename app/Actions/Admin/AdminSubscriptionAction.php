<?php

namespace App\Actions\Admin;

use App\Data\Admin\UpdateAdminSubscriptionData;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Repositories\Contracts\PlanRepositoryInterface;
use App\Repositories\Contracts\SubscriptionRepositoryInterface;

class AdminSubscriptionAction
{
    public function __construct(
        private SubscriptionRepositoryInterface $subscriptionRepository,
        private PlanRepositoryInterface $planRepository,
    ) {}

    public function index(
        int $perPage = 15,
        ?string $search = null,
        ?string $planId = null,
        ?string $status = null,
        ?string $salonId = null,
    ): \Illuminate\Contracts\Pagination\LengthAwarePaginator {
        return $this->subscriptionRepository->paginateForAdmin(
            $perPage,
            $search,
            $planId,
            $status,
            $salonId,
        );
    }

    public function show(string $subscriptionId): Subscription
    {
        $subscription = $this->subscriptionRepository->findById($subscriptionId);

        if (! $subscription) {
            throw new \RuntimeException('Abonnement introuvable.');
        }

        return $subscription;
    }

    public function update(string $subscriptionId, UpdateAdminSubscriptionData $data): Subscription
    {
        $subscription = $this->show($subscriptionId);
        $payload = $data->toArray();

        if ($payload === []) {
            throw new \RuntimeException('Aucune donnée à mettre à jour.');
        }

        if (isset($payload['plan_id']) && ! $this->planRepository->findById($payload['plan_id'])) {
            throw new \RuntimeException('Le plan sélectionné est introuvable.');
        }

        return $this->subscriptionRepository->update($subscription, $payload);
    }

    public function cancel(string $subscriptionId): Subscription
    {
        $subscription = $this->show($subscriptionId);

        return $this->subscriptionRepository->update($subscription, [
            'status' => SubscriptionStatus::Cancelled,
            'ends_at' => now()->toDateString(),
            'trial_ends_at' => null,
            'is_trial' => false,
        ]);
    }
}
