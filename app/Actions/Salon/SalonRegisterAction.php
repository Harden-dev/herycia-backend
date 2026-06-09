<?php

namespace App\Actions\Salon;

use App\Data\Salon\SalonRegisterData;
use App\Data\Salon\SalonRegisterOutcome;
use App\Events\SalonRegistered;
use App\Exceptions\PlanNotFoundException;
use App\Services\Auth\JwtService;
use App\Services\Salon\SalonRegistrationService;
use Illuminate\Support\Facades\RateLimiter;

class SalonRegisterAction
{
    private const RATE_LIMIT_KEY = 'salon-register';

    public function __construct(
        private SalonRegistrationService $registrationService,
        private JwtService $jwtService,
    ) {}

    public function execute(SalonRegisterData $data, string $ipAddress): SalonRegisterOutcome
    {
        $rateLimitKey = self::RATE_LIMIT_KEY.':'.$ipAddress;

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            throw new \Exception('Trop de tentatives d\'inscription. Réessayez dans une heure.');
        }

        RateLimiter::hit($rateLimitKey, 3600);

        try {
            $result = $this->registrationService->register($data);
        } catch (PlanNotFoundException $e) {
            throw $e;
        }

        $token = $this->jwtService->createToken($result['user']);

        event(new SalonRegistered(
            $result['salon'],
            $result['user'],
            $result['subscription'],
        ));

        return new SalonRegisterOutcome(
            salon: $result['salon'],
            user: $result['user'],
            subscription: $result['subscription'],
            accessToken: $token,
            tokenType: 'Bearer',
            expiresIn: $this->jwtService->getExpiresIn($token),
            expiresAt: $this->jwtService->getExpiresAt($token),
        );
    }
}
