<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Masque la documentation Swagger hors environnement local,
 * sauf activation explicite via API_DOCS_ENABLED=true (audit H10).
 */
class EnsureApiDocsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $enabled = app()->environment('local') || (bool) config('l5-swagger.defaults.docs_enabled', false);

        abort_unless($enabled, Response::HTTP_NOT_FOUND);

        return $next($request);
    }
}
