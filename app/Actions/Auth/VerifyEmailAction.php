<?php

namespace App\Actions\Auth;

use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Log;

class VerifyEmailAction
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {}

    public function execute(string $userId, string $hash): bool
    {
        try {
            $user = $this->userRepository->findById($userId);

            if (!$user) {
                throw new \Exception('Utilisateur non trouvé');
            }

            if ($user->hasVerifiedEmail()) {
                throw new \Exception('Email déjà vérifié');
            }

            if (!hash_equals((string) $hash, sha1($user->email))) {
                throw new \Exception('Lien de vérification invalide');
            }

            $user->markEmailAsVerified();

            Log::info('Email verified successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Error verifying email: ' . $e->getMessage());
            throw new \Exception($e->getMessage());
        }
    }
}

#salle numérique de l'UNA