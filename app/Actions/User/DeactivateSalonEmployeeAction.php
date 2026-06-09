<?php

namespace App\Actions\User;

use App\Enums\SalonStaffRole;
use App\Models\User;
use App\Repositories\Contracts\SubscriptionRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Auth\AuthService;
use App\Services\Salon\SalonContextService;

class DeactivateSalonEmployeeAction
{
    public function __construct(
        private SalonContextService $salonContext,
        private UserRepositoryInterface $userRepository,
        private SubscriptionRepositoryInterface $subscriptionRepository,
        private AuthService $authService,
    ) {}

    public function execute(string $employeeId): User
    {
        $employee = $this->salonContext->resolveSalonEmployee($employeeId);

        if ($employee->is_active) {
            return $this->deactivate($employee);
        }

        return $this->activate($employee);
    }

    private function deactivate(User $employee): User
    {
        $currentUser = $this->authService->getAuthenticatedUser();

        if ($employee->id === $currentUser->id) {
            throw new \RuntimeException('Vous ne pouvez pas désactiver votre propre compte.');
        }

        if ($employee->hasRole(SalonStaffRole::Admin)) {
            throw new \RuntimeException('Le compte administrateur ne peut pas être désactivé.');
        }

        $this->userRepository->update($employee, ['is_active' => false]);

        return $employee->refresh();
    }

    private function activate(User $employee): User
    {
        $subscription = $this->subscriptionRepository->findLatestBySalonId($employee->salon_id);

        if ($subscription?->plan === null) {
            throw new \RuntimeException('Aucun plan actif pour ce salon.');
        }

        $activeEmployees = $this->userRepository->countActiveBySalon($employee->salon_id);

        if (! $subscription->plan->allowsMoreEmployees($activeEmployees)) {
            throw new \RuntimeException('Limite d\'employés atteinte pour votre plan.');
        }

        $this->userRepository->update($employee, ['is_active' => true]);

        return $employee->refresh();
    }
}
