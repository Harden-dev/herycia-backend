<?php

namespace App\Actions\Auth;

use App\Data\ResetPasswordData;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Auth\RedisOtpService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ResetPasswordAction
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private RedisOtpService $redisOtpService,
    ) {}

    public function execute(ResetPasswordData $data): array
    {
        try {
            // 1. Vérifier le token temporaire
            $tokenKey = 'password_reset_token:' . $data->token;
            $tokenDataJson = Redis::get($tokenKey);

            if (!$tokenDataJson) {
                throw new \Exception('Token invalide ou expiré. Veuillez recommencer la réinitialisation.');
            }

            $tokenData = json_decode($tokenDataJson, true);

            // 2. Vérifier que l'email correspond
            if ($tokenData['email'] !== $data->email) {
                throw new \Exception('Les informations ne correspondent pas.');
            }

            // 3. Trouver l'utilisateur
            $user = $this->userRepository->findByEmail($data->email);

            if (!$user) {
                throw new \Exception('Utilisateur non trouvé');
            }

            // 4. Réinitialiser le mot de passe
            $user->password = Hash::make($data->password);
            $user->save();

            // 5. Nettoyer Redis
            Redis::del($tokenKey);
            Redis::del('otp:ratelimit:' . $data->email);
            Redis::del('otp:registration:attempts:' . $data->email);

            Log::info('Password reset successfully', [
                'email' => $data->email,
                'user_id' => $user->id,
            ]);

            return [
                'message' => 'Votre mot de passe a été réinitialisé avec succès',
            ];

        } catch (\Exception $e) {
            Log::error('Error resetting password: ' . $e->getMessage());
            throw new \Exception($e->getMessage());
        }
    }
}

