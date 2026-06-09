<?php

namespace App\Actions\Admin;

use App\Services\Auth\AuthService;

class AdminMeAction
{
    public function __construct(
        private AuthService $authService,
    ) {}

    public function execute(): \App\Models\User
    {
        return $this->authService->getAuthenticatedUser();
    }
}
