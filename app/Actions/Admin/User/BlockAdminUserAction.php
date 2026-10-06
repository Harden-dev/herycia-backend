<?php

namespace App\Actions\Admin\User;

use App\Enums\SalonStaffRole;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Auth\AuthService;
use RuntimeException;

class BlockAdminUserAction
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private AuthService $authService,
    ) {}

    public function execute(string $id): User
    {
        $user = $this->userRepository->findById($id);

        if (! $user) {
            throw new RuntimeException('Utilisateur introuvable.');
        }

        $authenticatedUser = $this->authService->getAuthenticatedUser();
        $nextStatus = ! $user->is_active;

        if ($user->id === $authenticatedUser->id && $nextStatus === false) {
            throw new RuntimeException('Vous ne pouvez pas bloquer votre propre compte.');
        }

        if ($user->isSuperAdmin() && $nextStatus === false && ! $authenticatedUser->isSuperAdmin()) {
            throw new RuntimeException('Seul un super administrateur peut bloquer ce compte.');
        }

        // Ne jamais bloquer le dernier super admin actif (perte d'accès à la plateforme).
        if ($user->isSuperAdmin() && $nextStatus === false
            && User::query()->where('role', SalonStaffRole::SuperAdmin)->where('is_active', true)->count() <= 1) {
            throw new RuntimeException('Impossible de bloquer le dernier super administrateur actif.');
        }

        $this->userRepository->toggleStatus($user, $nextStatus);

        return $user->refresh()->load('salon');
    }
}
