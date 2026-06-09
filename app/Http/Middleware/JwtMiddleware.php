<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
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
        } catch (\Throwable $e) {
            return $this->unauthorizedFromException($e);
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
            $e instanceof UnauthorizedHttpException => [
                'Token invalide ou non fourni: '.$e->getMessage(),
                'unauthorized',
            ],
            default => ['Erreur d\'authentification: '.$e->getMessage(), 'auth_error'],
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
