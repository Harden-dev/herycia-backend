<?php

namespace App\Actions\Auth;

use App\Services\Auth\JwtService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

class LogoutAction
{
    public function __construct(
        private JwtService $jwtService,
    ) {}
    public function execute(): void
    {
        try {
            $userId = Auth::id();

            $this->jwtService->logout();

            Log::info('User logged out successfully', [
                'user_id' => $userId,
            ]);
        } catch (\Throwable $th) {
            Log::error('Error logging out user: ' . $th->getMessage());
            throw new \Exception('Error logging out user: ' . $th->getMessage());
        }
    }
}
