<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;

class RedisOtpService
{
    private const PENDING_USER_PREFIX = 'pending_user:';
    private const OTP_EMAIL_PREFIX = 'otp:registration:email:';
    private const OTP_PHONE_PREFIX = 'otp:registration:phone:';
    private const OTP_RATELIMIT_PREFIX = 'otp:ratelimit:';
    private const OTP_ATTEMPTS_PREFIX = 'otp:registration:attempts:';

    private const PENDING_USER_TTL = 900;
    private const OTP_TTL = 600;
    private const RATELIMIT_TTL = 300;
    private const MAX_RESEND_ATTEMPTS = 3;
    private const MAX_VERIFY_ATTEMPTS = 5;

    /** Durée de vie de l'OTP inscription (secondes), pour réponses API */
    public function getRegistrationOtpTtlSeconds(): int
    {
        return self::OTP_TTL;
    }

    /**
     * Génère un code OTP à 6 chiffres
     */
    public function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Stocke les données temporaires de l'utilisateur dans Redis
     */
    public function storePendingUser(string $email, array $userData): bool
    {
        $key = self::PENDING_USER_PREFIX . $email;

        $data = json_encode([
            'first_name' => $userData['first_name'] ?? null,
            'last_name' => $userData['last_name'] ?? null,
            'email' => $userData['email'] ?? null,
            'phone' => $userData['phone'] ?? null,
            'password' => $userData['password'],
            'address' => $userData['address'] ?? null,
            'profile_picture' => $userData['profile_picture'] ?? null,
            'slug' => $userData['slug'],
            'created_at' => Carbon::now()->timestamp,
        ]);

        Redis::setex($key, self::PENDING_USER_TTL, $data);

        return true;
    }

    /**
     * Récupère les données temporaires de l'utilisateur depuis Redis
     */
    public function getPendingUser(string $identifier): ?array
    {
        $key = self::PENDING_USER_PREFIX . $identifier;
        $data = Redis::get($key);

        if ($data) {
            return json_decode($data, true);
        }

        // Si pas trouvé par email, chercher par phone dans tous les pending users
        $keys = Redis::keys(self::PENDING_USER_PREFIX . '*');
        foreach ($keys as $key) {
            $userData = json_decode(Redis::get($key), true);
            if (isset($userData['phone']) && $userData['phone'] === $identifier) {
                return $userData;
            }
        }

        return null;
    }

    /**
     * Stocke l'OTP dans Redis avec double clé (email + phone)
     */
    public function storeOtp(string $code, ?string $email, ?string $phone): bool
    {
        $otpData = json_encode([
            'email' => $email,
            'phone' => $phone,
            'code' => $code,
            'created_at' => Carbon::now()->timestamp,
        ]);

        // Stocker avec clé email si disponible
        if ($email) {
            $emailKey = self::OTP_EMAIL_PREFIX . $email;
            Redis::setex($emailKey, self::OTP_TTL, $otpData);
        }

        // Stocker avec clé phone si disponible
        if ($phone) {
            $phoneKey = self::OTP_PHONE_PREFIX . $phone;
            Redis::setex($phoneKey, self::OTP_TTL, $otpData);
        }

        return true;
    }

    /**
     * Récupère l'OTP depuis Redis
     */
    public function getOtp(string $identifier): ?array
    {
        // Essayer par email
        $emailKey = self::OTP_EMAIL_PREFIX . $identifier;
        $data = Redis::get($emailKey);

        if ($data) {
            return json_decode($data, true);
        }

        // Essayer par phone
        $phoneKey = self::OTP_PHONE_PREFIX . $identifier;
        $data = Redis::get($phoneKey);

        if ($data) {
            return json_decode($data, true);
        }

        return null;
    }

    /**
     * Vérifie le rate limiting pour l'envoi d'OTP
     */
    public function checkRateLimit(string $identifier): bool
    {
        $key = self::OTP_RATELIMIT_PREFIX . $identifier;
        $attempts = Redis::get($key);

        if ($attempts && (int)$attempts >= self::MAX_RESEND_ATTEMPTS) {
            return false; // Rate limit atteint
        }

        return true;
    }

    /**
     * Incrémente le compteur de rate limiting
     */
    public function incrementRateLimit(string $identifier): void
    {
        $key = self::OTP_RATELIMIT_PREFIX . $identifier;
        $current = Redis::get($key);

        if ($current) {
            Redis::incr($key);
        } else {
            Redis::setex($key, self::RATELIMIT_TTL, 1);
        }
    }

    /**
     * Vérifie les tentatives de vérification d'OTP
     */
    public function checkVerifyAttempts(string $identifier): bool
    {
        $key = self::OTP_ATTEMPTS_PREFIX . $identifier;
        $attempts = Redis::get($key);

        if ($attempts && (int)$attempts >= self::MAX_VERIFY_ATTEMPTS) {
            return false; // Trop de tentatives
        }

        return true;
    }

    /**
     * Réinitialise les tentatives de vérification
     */
    public function resetVerifyAttempts(string $identifier): void
    {
        Redis::del(self::OTP_ATTEMPTS_PREFIX . $identifier);
    }

    /**
     * Incrémente le compteur de tentatives de vérification
     */
    public function incrementVerifyAttempts(string $identifier): void
    {
        $key = self::OTP_ATTEMPTS_PREFIX . $identifier;
        $current = Redis::get($key);

        if ($current) {
            Redis::incr($key);
        } else {
            Redis::setex($key, self::OTP_TTL, 1);
        }
    }

    /**
     * Nettoie toutes les données Redis liées à un utilisateur (OTP, rate limit, tentatives)
     */
    public function cleanupUserData(?string $email = null, ?string $phone = null): void
    {
        if ($email) {
            Redis::del(self::PENDING_USER_PREFIX . $email);
            Redis::del(self::OTP_EMAIL_PREFIX . $email);
            Redis::del(self::OTP_RATELIMIT_PREFIX . $email);
            Redis::del(self::OTP_ATTEMPTS_PREFIX . $email);
        }
        if ($phone) {
            Redis::del(self::PENDING_USER_PREFIX . $phone);
            Redis::del(self::OTP_PHONE_PREFIX . $phone);
            Redis::del(self::OTP_RATELIMIT_PREFIX . $phone);
            Redis::del(self::OTP_ATTEMPTS_PREFIX . $phone);
        }
    }

    /**
     * Obtient le temps restant avant expiration de l'OTP
     */
    public function getOtpTtl(string $identifier): int
    {
        $emailKey = self::OTP_EMAIL_PREFIX . $identifier;
        $ttl = Redis::ttl($emailKey);

        if ($ttl > 0) {
            return $ttl;
        }

        $phoneKey = self::OTP_PHONE_PREFIX . $identifier;
        $ttl = Redis::ttl($phoneKey);

        return $ttl > 0 ? $ttl : 0;
    }
}
