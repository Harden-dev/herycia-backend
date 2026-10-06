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

    /** Compteur d'essais propre au flux de réinitialisation (ne pas partager avec l'inscription). */
    public const ATTEMPTS_PREFIX = 'otp:password_reset:attempts:';

    private const MAX_ATTEMPTS = 5;

    public function execute(string $email, string $code): array
    {
        $resetKey = 'otp:password_reset:email:'.$email;
        $attemptsKey = self::ATTEMPTS_PREFIX.$email;

        if ((int) Redis::get($attemptsKey) >= self::MAX_ATTEMPTS) {
            // Trop d'essais : le code est invalidé, il faut en redemander un.
            Redis::del($resetKey);
            throw new \Exception('Trop de tentatives. Veuillez demander un nouveau code.');
        }

        $otpDataJson = Redis::get($resetKey);

        if (! $otpDataJson) {
            throw new \Exception('Code expiré ou invalide. Veuillez demander un nouveau code.');
        }

        $otpData = json_decode($otpDataJson, true);

        if (! is_array($otpData) || ! hash_equals((string) ($otpData['code'] ?? ''), $code)) {
            if (Redis::incr($attemptsKey) === 1) {
                Redis::expire($attemptsKey, 600);
            }

            throw new \Exception('Code incorrect.');
        }

        $user = $this->userRepository->findByEmail($email);

        if (! $user || ! $user->is_active) {
            throw new \Exception('Code expiré ou invalide. Veuillez demander un nouveau code.');
        }

        // Token temporaire de réinitialisation (valide 15 min)
        $resetToken = Str::random(60);
        Redis::setex('password_reset_token:'.$resetToken, 900, json_encode([
            'email' => $email,
            'user_id' => $user->id,
            'created_at' => now()->timestamp,
        ]));

        // Code à usage unique
        Redis::del($resetKey);
        Redis::del($attemptsKey);

        Log::info('Password reset code verified successfully', ['user_id' => $user->id]);

        return [
            'token' => $resetToken,
            'expires_in' => 15, // minutes
            'message' => 'Code vérifié. Vous pouvez maintenant changer votre mot de passe.',
        ];
    }
}
