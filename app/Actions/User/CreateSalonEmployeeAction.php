<?php

namespace App\Actions\User;

use App\Data\User\CreateSalonEmployeeData;
use App\Models\User;
use App\Repositories\Contracts\SubscriptionRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Salon\SalonContextService;

class CreateSalonEmployeeAction
{
    public function __construct(
        private SalonContextService $salonContext,
        private UserRepositoryInterface $userRepository,
        private SubscriptionRepositoryInterface $subscriptionRepository,
    ) {}

    public function execute(CreateSalonEmployeeData $data): User
    {
        $salon = $this->salonContext->resolveAuthenticatedSalon();
        $subscription = $this->subscriptionRepository->findLatestBySalonId($salon->id);

        if ($subscription?->plan === null) {
            throw new \RuntimeException('Aucun plan actif pour ce salon.');
        }

        $activeEmployees = $this->userRepository->countActiveBySalon($salon->id);

        if (! $subscription->plan->allowsMoreEmployees($activeEmployees)) {
            throw new \RuntimeException('Limite d\'employés atteinte pour votre plan.');
        }

        return $this->userRepository->create([
            'salon_id' => $salon->id,
            'name' => $data->name,
            'phone' => $data->phone,
            'email' => $data->email,
            'password' => $data->password,
            'role' => $data->role,
            'is_active' => true,
        ]);
    }
}
