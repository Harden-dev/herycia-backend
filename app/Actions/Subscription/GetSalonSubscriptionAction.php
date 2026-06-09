<?php

namespace App\Actions\Subscription;

use App\Models\Subscription;
use App\Repositories\Contracts\SubscriptionRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Salon\SalonContextService;

class GetSalonSubscriptionAction
{
    public function __construct(
        private SalonContextService $salonContext,
        private SubscriptionRepositoryInterface $subscriptionRepository,
        private UserRepositoryInterface $userRepository,
    ) {}

    /**
     * @return array{subscription: Subscription, active_employees: int}
     */
    public function execute(): array
    {
        $salon = $this->salonContext->resolveAuthenticatedSalon();
        $subscription = $this->subscriptionRepository->findCurrentBySalonId($salon->id);

        if ($subscription === null) {
            throw new \RuntimeException('Aucun abonnement trouvé pour ce salon.');
        }

        return [
            'subscription' => $subscription,
            'active_employees' => $this->userRepository->countActiveBySalon($salon->id),
        ];
    }
}
