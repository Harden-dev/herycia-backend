<?php

namespace App\Actions\Auth;

use App\Data\Auth\MeData;
use App\Repositories\Contracts\SubscriptionRepositoryInterface;
use App\Services\Auth\AuthService;

class GetMeAction
{
    public function __construct(
        private AuthService $authService,
        private SubscriptionRepositoryInterface $subscriptionRepository,
    ) {}

    public function execute(): MeData
    {
        $user = $this->authService->getAuthenticatedUser();
        $user->load('salon');

        $subscription = $user->salon_id
            ? $this->subscriptionRepository->findLatestBySalonId($user->salon_id)
            : null;

        return new MeData($user, $subscription);
    }
}
