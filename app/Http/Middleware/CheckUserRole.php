<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

class CheckUserRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = JWTAuth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non authentifié',
            ], Response::HTTP_UNAUTHORIZED);
        }


        if (!$user->relationLoaded('role')) {
            $user->load('role');
        }

        if (!$user->hasRole('user')) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé. Cette action nécessite le rôle utilisateur.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
