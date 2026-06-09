<?php

namespace App\Actions\Auth;

use App\Events\UserCreated;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Audit\AuditLogService;
use App\Services\Auth\JwtService;
use App\Services\Auth\RedisOtpService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VerifyOtpAction
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private RedisOtpService $redisOtpService,
        private JwtService $jwtService,
        private AuditLogService $auditLogService,
    ) {}

    public function execute(string $login, string $code): array
    {
        DB::beginTransaction();

        try {
            if (!$this->redisOtpService->checkVerifyAttempts($login)) {
                throw new \Exception('Trop de tentatives de vérification. Veuillez demander un nouveau code.');
            }

            $otpData = $this->redisOtpService->getOtp($login);

            if (!$otpData) {
                throw new \Exception('Code OTP expiré ou invalide. Veuillez demander un nouveau code.');
            }

            if ($otpData['code'] !== $code) {
                $this->redisOtpService->incrementVerifyAttempts($login);
                throw new \Exception('Code OTP incorrect. Veuillez réessayer.');
            }

            // User déjà en base (créé à l'inscription)
            $user = $this->userRepository->findByEmail($login)
                ?? $this->userRepository->findByPhone($login);

            if (! $user || $user->is_active) {
                throw new \Exception('Session expirée. Veuillez recommencer l\'inscription.');
            }

            $updates = ['is_active' => true];
            if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
                $updates['email_verified_at'] = now();
            } else {
                $updates['phone_verified_at'] = now();
            }

            $this->userRepository->update($user, $updates);

            Log::info('User verified successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'phone' => $user->phone,
            ]);

            event(new UserCreated($user));

            $this->auditLogService->log(
                'user.created',
                User::class,
                $user->id,
                'Nouvel utilisateur inscrit',
                null,
                null,
                $user->id,
            );

            $this->redisOtpService->cleanupUserData($user->email, $user->phone);

            $token = $this->jwtService->createToken($user);

            DB::commit();

            return [
                'user' => $user->fresh(['role']),
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => $this->jwtService->getExpiresIn($token),
                'expires_at' => $this->jwtService->getExpiresAt($token),
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error verifying OTP: ' . $e->getMessage(), ['login' => $login]);
            throw $e;
        }
    }
}
