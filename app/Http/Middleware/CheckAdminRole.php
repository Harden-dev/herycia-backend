<?php

namespace App\Http\Middleware;

use App\Enums\SalonStaffRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

class CheckAdminRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = JWTAuth::user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non authentifié',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (! $user->hasRole(SalonStaffRole::Admin)) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé. Cette action nécessite le rôle administrateur.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
