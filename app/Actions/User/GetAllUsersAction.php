<?php

namespace App\Actions\User;

use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetAllUsersAction
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {}

    public function execute(int $perPage = 15, ?string $search = null, ?bool $isActive = null): LengthAwarePaginator
    {
        return $this->userRepository->getPaginated($perPage, $search, $isActive);
    }
}

