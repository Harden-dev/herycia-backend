<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenBlacklistedException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class JwtMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $this->authenticate() ?? $next($request);
    }

    private function authenticate(): ?Response
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (! $user) {
                return $this->unauthorized('L\'utilisateur n\'a pas été trouvé');
            }

            $issuedAt = JWTAuth::getPayload()->get('iat');
        } catch (\Throwable $e) {
            return $this->unauthorizedFromException($e);
        }

        return $this->assertAccountUsable($user, is_numeric($issuedAt) ? (int) $issuedAt : null);
    }

    /**
     * Vérifie à chaque requête l'état réel du compte (audit H2/H3) : un jeton reste
     * cryptographiquement valide jusqu'à expiration, mais ne doit plus donner accès
     * après un blocage, un changement de mot de passe ou la suspension du salon.
     */
    private function assertAccountUsable(User $user, ?int $issuedAt): ?Response
    {
        if (! $user->is_active) {
            return $this->unauthorized('Votre compte est désactivé', 'account_disabled');
        }

        if ($user->password_changed_at !== null
            && ($issuedAt === null || $issuedAt < $user->password_changed_at->getTimestamp())) {
            return $this->unauthorized('Session expirée, veuillez vous reconnecter', 'token_revoked');
        }

        if ($user->salon_id !== null) {
            $salon = $user->salon;

            if ($salon === null || ! $salon->isOperational()) {
                return response()->json([
                    'success' => false,
                    'message' => $salon?->isSuspended()
                        ? 'Ce salon est suspendu. Contactez le support Salono.'
                        : 'Ce salon n\'est plus actif. Contactez le support Salono.',
                    'error' => $salon?->isSuspended() ? 'salon_suspended' : 'salon_inactive',
                ], Response::HTTP_FORBIDDEN);
            }
        }

        return null;
    }

    private function unauthorizedFromException(\Throwable $e): Response
    {
        [$message, $error] = match (true) {
            $e instanceof TokenExpiredException => ['Le token a expiré', 'token_expired'],
            $e instanceof TokenInvalidException => ['Le token est invalide', 'token_invalid'],
            $e instanceof TokenBlacklistedException => ['Le token a été blacklisté', 'token_blacklisted'],
            $e instanceof JWTException => ['Token non fourni ou invalide', 'token_error'],
            default => ['Token invalide ou non fourni', 'unauthorized'],
        };

        return $this->unauthorized($message, $error);
    }

    private function unauthorized(string $message, ?string $error = null): Response
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($error !== null) {
            $payload['error'] = $error;
        }

        return response()->json($payload, Response::HTTP_UNAUTHORIZED);
    }
}
