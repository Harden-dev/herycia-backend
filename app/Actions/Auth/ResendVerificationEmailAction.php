<?php

namespace App\Actions\Auth;

use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Log;

class ResendVerificationEmailAction
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {}

    public function execute(string $email): bool
    {
        try {
            $user = $this->userRepository->findByEmail($email);

            if (!$user) {
                throw new \Exception('Utilisateur non trouvé');
            }

            if ($user->hasVerifiedEmail()) {
                throw new \Exception('Email déjà vérifié');
            }

            $user->sendEmailVerificationNotification();

            Log::info('Verification email resent', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Error resending verification email: ' . $e->getMessage());
            throw new \Exception($e->getMessage());
        }
    }
}

