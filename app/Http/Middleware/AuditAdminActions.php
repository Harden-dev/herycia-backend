<?php

namespace App\Http\Middleware;

use App\Services\Audit\AuditLogService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Journal d'audit des actions du super admin (audit M8) : toute requête modifiante
 * (POST/PUT/PATCH/DELETE) de l'espace /admin est tracée avec son auteur, sa cible,
 * les champs envoyés (sans mot de passe) et le code de réponse.
 */
class AuditAdminActions
{
    private const SENSITIVE_KEYS = ['password', 'password_confirmation', 'new_password', 'token', 'temporary_password'];

    public function __construct(
        private AuditLogService $auditLogService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $this->record($request, $response);
        }

        return $response;
    }

    private function record(Request $request, Response $response): void
    {
        try {
            $input = collect($request->except(self::SENSITIVE_KEYS))
                ->map(fn ($value) => is_scalar($value) || $value === null ? $value : json_encode($value))
                ->all();

            $path = $request->route()?->uri() ?? $request->path();

            $this->auditLogService->log(
                action: 'admin.'.strtolower($request->method()).':'.$path,
                entityType: null,
                entityId: is_string($request->route('id')) ? $request->route('id') : null,
                description: sprintf('%s %s → HTTP %d', $request->method(), $request->path(), $response->getStatusCode()),
                oldValues: $input !== [] ? [] : null,
                newValues: $input !== [] ? $input : null,
                userId: JWTAuth::user()?->id,
            );
        } catch (\Throwable $e) {
            // L'audit ne doit jamais faire échouer l'action d'administration.
            Log::error('Audit admin action failed: '.$e->getMessage());
        }
    }
}
