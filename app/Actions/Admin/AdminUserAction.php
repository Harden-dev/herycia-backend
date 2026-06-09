<?php

namespace App\Actions\Admin;

use App\Actions\Admin\User\BlockAdminUserAction;
use App\Actions\Admin\User\GetAdminUserAction;
use App\Actions\Admin\User\ListAdminUsersAction;
use App\Actions\Admin\User\ResetAdminUserPasswordAction;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;

class AdminUserAction
{
    public function __construct(
        private ListAdminUsersAction $listAdminUsersAction,
        private GetAdminUserAction $getAdminUserAction,
        private BlockAdminUserAction $blockAdminUserAction,
        private ResetAdminUserPasswordAction $resetAdminUserPasswordAction,
        private UserRepositoryInterface $userRepository,
    ) {}

    public function index(
        int $perPage = 15,
        ?string $search = null,
        ?bool $isActive = null,
        ?string $userType = null,
    ): LengthAwarePaginator {
        return $this->listAdminUsersAction->execute($perPage, $search, $isActive, $userType);
    }

    public function show(string $userId): User
    {
        return $this->getAdminUserAction->execute($userId);
    }

    public function block(string $userId): User
    {
        return $this->blockAdminUserAction->execute($userId);
    }

    /** @return array{user: User, password: string|null} */
    public function resetPassword(string $userId, ?string $newPassword): array
    {
        if ($newPassword !== null) {
            $user = $this->getAdminUserAction->execute($userId);
            $this->userRepository->update($user, [
                'password' => Hash::make($newPassword),
            ]);

            return [
                'user' => $user->refresh()->load('salon'),
                'password' => null,
            ];
        }

        $result = $this->resetAdminUserPasswordAction->execute($userId);

        return [
            'user' => $result['user'],
            'password' => $result['temporary_password'],
        ];
    }
}
