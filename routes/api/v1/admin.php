<?php

use App\Http\Controllers\API\V1\Admin\AdminBillingPaymentController;
use App\Http\Controllers\API\V1\Admin\AdminMeController;
use App\Http\Controllers\API\V1\Admin\AdminPlanController;
use App\Http\Controllers\API\V1\Admin\AdminSalonController;
use App\Http\Controllers\API\V1\Admin\AdminStatsController;
use App\Http\Controllers\API\V1\Admin\AdminSubscriptionController;
use App\Http\Controllers\API\V1\Admin\AdminUserController;
use App\Http\Middleware\AuditAdminActions;
use Illuminate\Support\Facades\Route;

Route::middleware(['jwt.verified', 'throttle:api-user', 'role.super_admin', AuditAdminActions::class])->prefix('admin')->group(function () {
    Route::get('/stats/overview', [AdminStatsController::class, 'overview']);
    Route::get('/stats/salons-by-city', [AdminStatsController::class, 'salonsByCity']);
    Route::get('/me', [AdminMeController::class, 'me']);

    Route::get('/salons', [AdminSalonController::class, 'index']);
    Route::prefix('salons/{id}')->group(function () {
        Route::get('/', [AdminSalonController::class, 'show']);
        Route::put('/', [AdminSalonController::class, 'update']);
        Route::patch('/suspend', [AdminSalonController::class, 'suspend']);
        Route::patch('/deactivate', [AdminSalonController::class, 'deactivate']);
        Route::delete('/', [AdminSalonController::class, 'destroy']);
    });

    Route::get('/plans', [AdminPlanController::class, 'index']);
    Route::post('/plans', [AdminPlanController::class, 'store']);
    Route::prefix('plans/{id}')->group(function () {
        Route::get('/', [AdminPlanController::class, 'show']);
        Route::put('/', [AdminPlanController::class, 'update']);
        Route::patch('/archive', [AdminPlanController::class, 'archive']);
    });

    Route::get('/subscriptions', [AdminSubscriptionController::class, 'index']);
    Route::prefix('subscriptions/{id}')->group(function () {
        Route::get('/', [AdminSubscriptionController::class, 'show']);
        Route::put('/', [AdminSubscriptionController::class, 'update']);
        Route::patch('/cancel', [AdminSubscriptionController::class, 'cancel']);
    });

    Route::get('/billing-payments', [AdminBillingPaymentController::class, 'index']);
    Route::get('/billing-payments/{id}', [AdminBillingPaymentController::class, 'show']);

    Route::get('/users', [AdminUserController::class, 'index']);
    Route::prefix('users/{id}')->group(function () {
        Route::get('/', [AdminUserController::class, 'show']);
        Route::patch('/block', [AdminUserController::class, 'block']);
        Route::patch('/reset-password', [AdminUserController::class, 'resetPassword']);
    });
});
