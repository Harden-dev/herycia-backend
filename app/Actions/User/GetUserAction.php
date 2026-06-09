<?php

namespace App\Actions\User;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;

class GetUserAction
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {}

    public function execute(string $userId): User
    {
        $user = $this->userRepository->findById($userId);

        if (!$user) {
            throw new \Exception('Utilisateur non trouvé');
        }

        return $user;
    }
}

