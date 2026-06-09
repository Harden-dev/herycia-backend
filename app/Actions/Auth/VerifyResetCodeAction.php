<?php

namespace App\Actions\Auth;

use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Auth\RedisOtpService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class VerifyResetCodeAction
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private RedisOtpService $redisOtpService,
    ) {}

    public function execute(string $email, string $code): array
    {
        try {
            // 1. Vérifier les tentatives
            if (!$this->redisOtpService->checkVerifyAttempts($email)) {
                throw new \Exception('Trop de tentatives. Veuillez demander un nouveau code.');
            }

            // 2. Récupérer l'OTP depuis Redis
            $resetKey = 'otp:password_reset:email:' . $email;
            $otpDataJson = Redis::get($resetKey);

            if (!$otpDataJson) {
                throw new \Exception('Code expiré ou invalide. Veuillez demander un nouveau code.');
            }

            $otpData = json_decode($otpDataJson, true);

            // 3. Vérifier le code
            if ($otpData['code'] !== $code) {
                // Incrémenter les tentatives
                $this->redisOtpService->incrementVerifyAttempts($email);
                throw new \Exception('Code incorrect.');
            }

            // 4. Vérifier que l'utilisateur existe
            $user = $this->userRepository->findByEmail($email);

            if (!$user) {
                throw new \Exception('Utilisateur non trouvé');
            }

            // 5. Générer un token temporaire pour le reset (valide 15 min)
            $resetToken = Str::random(60);
            $tokenKey = 'password_reset_token:' . $resetToken;

            $tokenData = json_encode([
                'email' => $email,
                'user_id' => $user->id,
                'created_at' => now()->timestamp,
            ]);

            Redis::setex($tokenKey, 900, $tokenData); // 15 minutes

            // 6. Supprimer l'OTP (déjà utilisé)
            Redis::del($resetKey);

            Log::info('Password reset code verified successfully', [
                'email' => $email,
                'user_id' => $user->id,
            ]);

            return [
                'token' => $resetToken,
                'expires_in' => 15, // minutes
                'message' => 'Code vérifié. Vous pouvez maintenant changer votre mot de passe.',
            ];

        } catch (\Exception $e) {
            Log::error('Error verifying reset code: ' . $e->getMessage());
            throw $e;
        }
    }
}

