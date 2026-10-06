<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\CheckAdminRole;
use App\Http\Middleware\CheckSuperAdminRole;
use App\Http\Middleware\CheckSubscription;
use App\Http\Middleware\EnsureSalonStaffRole;
use App\Http\Middleware\JwtMiddleware;
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            // Alias distinct de 'jwt.auth', réservé (et écrasé) par le paquet tymon/jwt-auth
            'jwt.verified' => JwtMiddleware::class,
            'role.admin' => CheckAdminRole::class,
            'role.super_admin' => CheckSuperAdminRole::class,
            'subscription.active' => CheckSubscription::class,
            'role.salon' => EnsureSalonStaffRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Http\Exceptions\ThrottleRequestsException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Trop de tentatives. Veuillez réessayer plus tard.',
                'error' => 'too_many_requests',
            ], 429, $e->getHeaders());
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Non autorisé',
            ], 401);
        });
    })->create();
