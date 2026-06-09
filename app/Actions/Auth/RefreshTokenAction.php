<?php

namespace App\Actions\Auth;

use App\Services\Auth\JwtService;
use Illuminate\Support\Facades\Log;

class RefreshTokenAction
{
    public function __construct(
        private JwtService $jwtService,
    ) {}

    public function execute(string $token): string
    {
        try {
            $token = $this->jwtService->refreshToken($token);
            return $token;
        } catch (\Throwable $th) {
            Log::error('Error refreshing token: ' . $th->getMessage());
            throw new \Exception('Error refreshing token: ' . $th->getMessage());
        }
    }
}
