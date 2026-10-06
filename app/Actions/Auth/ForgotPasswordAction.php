<?php

namespace App\Actions\Auth;

use App\Data\ForgotPasswordData;
use App\Notifications\OtpNotification;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Auth\RedisOtpService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Redis;

class ForgotPasswordAction
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private RedisOtpService $redisOtpService,
    ) {}

    /** Réponse identique que le compte existe ou non (audit H9 : pas d'énumération). */
    private const NEUTRAL_MESSAGE = 'Si cet email existe, un code de réinitialisation a été envoyé';

    public function execute(ForgotPasswordData $data): array
    {
        $neutral = [
            'message' => self::NEUTRAL_MESSAGE,
            'expires_in' => 10,
        ];

        $user = $this->userRepository->findByEmail($data->email);

        if (! $user || ! $user->is_active) {
            return $neutral;
        }

        // Limite atteinte : on n'envoie rien mais on ne révèle pas l'existence du compte.
        if (! $this->redisOtpService->checkRateLimit($data->email)) {
            Log::notice('Password reset OTP rate limited');

            return $neutral;
        }

        $otpCode = $this->redisOtpService->generateCode();

        $resetKey = 'otp:password_reset:email:'.$data->email;
        $otpData = json_encode([
            'email' => $data->email,
            'code' => $otpCode,
            'created_at' => now()->timestamp,
        ]);

        Redis::setex($resetKey, 600, $otpData); // 10 minutes
        Redis::del(VerifyResetCodeAction::ATTEMPTS_PREFIX.$data->email);

        $this->redisOtpService->incrementRateLimit($data->email);

        Notification::route('mail', $data->email)
            ->notify(new OtpNotification($otpCode, 'email', 'password_reset'));

        Log::info('Password reset OTP sent', ['user_id' => $user->id]);

        return $neutral;
    }
}
