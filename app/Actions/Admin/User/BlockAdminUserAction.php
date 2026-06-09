<?php

namespace App\Actions\Admin\User;

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

        $this->userRepository->toggleStatus($user, $nextStatus);

        return $user->refresh()->load('salon');
    }
}
