<?php

namespace App\Actions\Auth;

use App\Notifications\OtpNotification;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Auth\RedisOtpService;
use App\Services\Sms\TwilioOtpSmsService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class ResendOtpAction
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private RedisOtpService $redisOtpService,
    ) {}

    public function execute(string $login): array
    {
        if (!$this->redisOtpService->checkRateLimit($login)) {
            $ttl = $this->redisOtpService->getOtpTtl($login);
            throw new \Exception('Trop de tentatives d\'envoi. Veuillez réessayer dans ' . ceil($ttl / 60) . ' minutes.');
        }

        $user = $this->userRepository->findByEmail($login)
            ?? $this->userRepository->findByPhone($login);

        if (! $user || $user->is_active) {
            throw new \Exception('Session expirée. Veuillez recommencer l\'inscription.');
        }

        $email = $user->email;
        $phone = $user->phone;

        $otpCode = $this->redisOtpService->generateCode();
        $this->redisOtpService->storeOtp($otpCode, $email, $phone);
        $this->redisOtpService->incrementRateLimit($login);
        $this->redisOtpService->resetVerifyAttempts($login);

        if ($email) {
            Notification::route('mail', $email)->notify(new OtpNotification($otpCode, 'email'));
        }
        if ($phone) {
            app(TwilioOtpSmsService::class)->sendRegistrationOtp($phone, $otpCode);
        }

        Log::info('OTP resent successfully', ['identifier' => $login]);

        $channel = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'sms';

        return [
            'channel' => $channel,
            'login' => $login,
            'expires_in' => $this->redisOtpService->getRegistrationOtpTtlSeconds(),
            'email' => $email,
            'phone' => $phone,
        ];
    }
}
