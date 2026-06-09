<?php

namespace App\Http\Middleware;

use App\Enums\SalonStaffRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

class CheckSuperAdminRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = JWTAuth::user();

        if ($user === null) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non authentifié',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (! $user->hasRole(SalonStaffRole::SuperAdmin)) {
            return response()->json([
                'success' => false,
                'message' => 'Accès réservé aux super administrateurs',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
