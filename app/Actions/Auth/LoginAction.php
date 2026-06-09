<?php

namespace App\Actions\Auth;

use App\Data\LoginData;
use App\Models\User;
use App\Services\Auth\AuthService;
use App\Services\Auth\JwtService;
use Illuminate\Support\Facades\Log;

class LoginAction
{
    public function __construct(
        private AuthService $authService,
        private JwtService $jwtService,
    ) {}
    public function execute(LoginData $loginData): array
    {
        $user = $this->authService->login($loginData->login, $loginData->password);

        // if(!$user->hasVerifiedEmail()){
        //     throw new \Exception('Veuillez vérifier votre email avant de vous connecter');
        // }

        $token = $this->jwtService->createToken($user);

        Log::info('User logged in successfully', [
            'user_id' => $user->id,
            'login' => $user->email ?? $user->phone,
            'token' => $token,
        ]);

        return [
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $this->jwtService->getExpiresIn($token),
            'expires_at' => $this->jwtService->getExpiresAt($token),
        ];
    }
}
