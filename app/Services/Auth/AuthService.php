<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Support\IvoryCoastPhone;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthService
{
    public function __construct(
        private JwtService $jwtService,
    ) {}

    public function login(string $login, string $password): User
    {
        $user = filter_var($login, FILTER_VALIDATE_EMAIL)
            ? User::where('email', $login)->first()
            : User::where('phone', IvoryCoastPhone::normalize($login))->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw new \Exception('Identifiants invalides');
        }

        if (! $user->is_active) {
            throw new \Exception('Votre compte est désactivé');
        }

        if ($user->salon_id !== null && ($user->salon === null || ! $user->salon->isOperational())) {
            throw new \Exception($user->salon?->isSuspended()
                ? 'Ce salon est suspendu. Contactez le support Salono.'
                : 'Ce salon n\'est plus actif. Contactez le support Salono.');
        }

        Log::info('User logged in successfully', ['user_id' => $user->id]);

        return $user;
    }

    // obtenir le user connecté
    public function getAuthenticatedUser(): User
    {
        try {
            return Auth::user() ?? throw new \Exception('Utilisateur non connecté');
        } catch (\Throwable $th) {
            Log::error('Error getting authenticated user: ' . $th->getMessage());
            throw new \Exception('Error getting authenticated user: ' . $th->getMessage());
        }
    }
}
