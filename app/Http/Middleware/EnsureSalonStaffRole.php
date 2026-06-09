<?php

namespace App\Http\Middleware;

use App\Enums\SalonStaffRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

class EnsureSalonStaffRole
{
    /** @param  string  ...$roles  Allowed role values (e.g. admin, receptionist) */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = JWTAuth::user();

        if ($user === null) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non authentifié',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $allowedRoles = array_map(
            static fn (string $role): SalonStaffRole => SalonStaffRole::from($role),
            $roles,
        );

        if (! $user->hasAnyRole($allowedRoles)) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé. Vous n\'avez pas les droits nécessaires.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
