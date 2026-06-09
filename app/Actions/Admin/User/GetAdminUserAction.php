<?php

namespace App\Actions\Admin\User;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use RuntimeException;

class GetAdminUserAction
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {}

    public function execute(string $id): User
    {
        $user = $this->userRepository->findById($id);

        if (! $user) {
            throw new RuntimeException('Utilisateur introuvable.');
        }

        return $user->load('salon');
    }
}
