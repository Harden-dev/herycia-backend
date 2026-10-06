<?php

namespace App\Actions\Auth;

use App\Services\Auth\JwtService;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

class RefreshTokenAction
{
    public function __construct(
        private JwtService $jwtService,
    ) {}

    public function execute(string $token): string
    {
        try {
            $token = $this->jwtService->refreshToken($token);

            // Le rafraîchissement ne doit pas prolonger la session d'un compte bloqué,
            // d'un mot de passe changé depuis (iat d'origine conservé) ou d'un salon suspendu.
            $payload = JWTAuth::setToken($token)->getPayload();
            $user = User::query()->find($payload->get('sub'));
            $changedAt = $user?->password_changed_at;
            $salonBlocked = $user?->salon_id !== null && ($user?->salon === null || ! $user->salon->isOperational());

            if ($user === null || ! $user->is_active || $salonBlocked
                || ($changedAt !== null && (int) $payload->get('iat') < $changedAt->getTimestamp())) {
                JWTAuth::setToken($token)->invalidate();
                throw new \RuntimeException('Session expirée, veuillez vous reconnecter.');
            }

            return $token;
        } catch (\Throwable $th) {
            Log::error('Error refreshing token: ' . $th->getMessage());
            throw new \Exception('Impossible de rafraîchir la session, veuillez vous reconnecter.');
        }
    }
}
