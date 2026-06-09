<?php

namespace App\Actions\Auth;

use App\Data\ForgotPasswordData;
use App\Notifications\OtpNotification;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Auth\RedisOtpService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class ForgotPasswordAction
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private RedisOtpService $redisOtpService,
    ) {}

    public function execute(ForgotPasswordData $data): array
    {
        try {
            $user = $this->userRepository->findByEmail($data->email);

            if (!$user) {
                // Pour la sécurité, on ne dit pas que l'utilisateur n'existe pas
                return [
                    'message' => 'Si cet email existe, un code de réinitialisation a été envoyé',
                ];
            }

            // Vérifier le rate limiting
            if (!$this->redisOtpService->checkRateLimit($data->email)) {
                throw new \Exception('Trop de tentatives. Veuillez réessayer dans 5 minutes.');
            }

            // Générer un code OTP pour le reset password
            $otpCode = $this->redisOtpService->generateCode();

            // Stocker l'OTP dans Redis (clé différente pour le reset password)
            $resetKey = 'otp:password_reset:email:' . $data->email;
            $otpData = json_encode([
                'email' => $data->email,
                'code' => $otpCode,
                'created_at' => now()->timestamp,
            ]);
            \Illuminate\Support\Facades\Redis::setex($resetKey, 600, $otpData); // 10 minutes

            // Incrémenter le rate limit
            $this->redisOtpService->incrementRateLimit($data->email);

            // Envoyer le code par email
            Notification::route('mail', $data->email)
                ->notify(new OtpNotification($otpCode, 'email', 'password_reset'));

            Log::info('Password reset OTP sent', [
                'email' => $data->email,
            ]);

            return [
                'message' => 'Un code de réinitialisation a été envoyé à votre adresse email',
                'expires_in' => 10, // minutes
            ];

        } catch (\Exception $e) {
            Log::error('Error sending password reset OTP: ' . $e->getMessage());
            throw new \Exception($e->getMessage());
        }
    }
}

