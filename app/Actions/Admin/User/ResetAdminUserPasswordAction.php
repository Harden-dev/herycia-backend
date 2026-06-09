<?php

namespace App\Actions\Admin\User;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class ResetAdminUserPasswordAction
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {}

    /** @return array{user: User, temporary_password: string} */
    public function execute(string $id): array
    {
        $user = $this->userRepository->findById($id);

        if (! $user) {
            throw new RuntimeException('Utilisateur introuvable.');
        }

        $temporaryPassword = Str::password(12);

        $this->userRepository->update($user, [
            'password' => Hash::make($temporaryPassword),
        ]);

        return [
            'user' => $user->refresh()->load('salon'),
            'temporary_password' => $temporaryPassword,
        ];
    }
}
