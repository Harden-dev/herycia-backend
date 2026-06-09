<?php

namespace App\Actions\Admin\User;

use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListAdminUsersAction
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {}

    public function execute(
        int $perPage = 15,
        ?string $search = null,
        ?bool $isActive = null,
        ?string $userType = null,
    ): LengthAwarePaginator {
        return $this->userRepository->paginateForAdmin(
            $perPage,
            $search,
            $isActive,
            $userType,
        );
    }
}
