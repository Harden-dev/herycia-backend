<?php

namespace App\Actions\Admin\Me;

use App\Models\User;
use App\Services\Auth\AuthService;

class GetAdminMeAction
{
    public function __construct(
        private AuthService $authService,
    ) {}

    public function execute(): User
    {
        return $this->authService->getAuthenticatedUser()->load('salon');
    }
}
