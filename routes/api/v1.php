<?php
use App\Http\Controllers\API\V1\Appointment\AppointmentController;
use App\Http\Controllers\API\V1\Auth\AuthController;
use App\Http\Controllers\API\V1\Client\ClientController;
use App\Http\Controllers\API\V1\Dashboard\DashboardController;
use App\Http\Controllers\API\V1\Payment\PaystackCallbackController;
use App\Http\Controllers\API\V1\Payment\PaymentController;
use App\Http\Controllers\API\V1\Plan\PlanController;
use App\Http\Controllers\API\V1\Queue\QueueController;
use App\Http\Controllers\API\V1\Subscription\SubscriptionController;
use App\Http\Controllers\API\V1\Salon\SalonController;
use App\Http\Controllers\API\V1\Service\ServiceController;
use App\Http\Controllers\API\V1\User\UserController;

use Illuminate\Support\Facades\Route;


Route::prefix('v1')->group(function () {
    require __DIR__.'/v1/admin.php';


    Route::get('/plans', [PlanController::class, 'index']);
    Route::get('/payment/callback', PaystackCallbackController::class);

    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])
            ->middleware('throttle:5,60');
        Route::post('/login', [AuthController::class, 'login']);

        Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
        Route::post('/resend-otp', [AuthController::class, 'resendOtp']);

        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/verify-reset-code', [AuthController::class, 'verifyResetCode']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    });
    Route::middleware(['jwt.auth'])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        Route::middleware('role.admin')->prefix('subscription')->group(function () {
            Route::get('/', [SubscriptionController::class, 'show']);
            Route::post('/initialize', [SubscriptionController::class, 'initialize']);
            Route::post('/simulate-payment', [SubscriptionController::class, 'simulatePayment']);
        });
    });

    Route::middleware(['jwt.auth', 'subscription.active'])->group(function () {
        Route::middleware('role.admin')->prefix('salon')->group(function () {
            Route::get('/', [SalonController::class, 'show']);
            Route::put('/', [SalonController::class, 'update']);
            Route::get('/booking-qr', [SalonController::class, 'bookingQr']);
            Route::post('/logo', [SalonController::class, 'uploadLogo']);
        });

        Route::middleware('role.admin')->group(function () {
            Route::get('/users', [UserController::class, 'index']);
            Route::post('/users', [UserController::class, 'store']);
            Route::get('/users/{id}', [UserController::class, 'show']);
            Route::put('/users/{id}', [UserController::class, 'update']);
            Route::delete('/users/{id}', [UserController::class, 'destroy']);
            Route::get('/payments/summary', [PaymentController::class, 'summary']);

            Route::post('/services', [ServiceController::class, 'store']);
            Route::put('/services/{id}', [ServiceController::class, 'update']);
            Route::delete('/services/{id}', [ServiceController::class, 'destroy']);
        });

        Route::get('/services', [ServiceController::class, 'index']);

        Route::get('/clients', [ClientController::class, 'index']);
        Route::post('/clients', [ClientController::class, 'store']);
        Route::get('/clients/{id}', [ClientController::class, 'show']);
        Route::put('/clients/{id}', [ClientController::class, 'update']);

        Route::get('/appointments', [AppointmentController::class, 'index']);
        Route::post('/appointments', [AppointmentController::class, 'store']);
        Route::get('/appointments/{id}', [AppointmentController::class, 'show']);
        Route::put('/appointments/{id}/status', [AppointmentController::class, 'updateStatus']);
        Route::delete('/appointments/{id}', [AppointmentController::class, 'destroy']);

        Route::middleware('role.salon:admin,receptionist')->group(function () {
            Route::get('/payments', [PaymentController::class, 'index']);
            Route::post('/payments', [PaymentController::class, 'store']);
        });

        Route::prefix('dashboard')->group(function () {
            Route::get('/overview', [DashboardController::class, 'overview']);
            Route::middleware('subscription.active:analytics')->group(function () {
                Route::get('/clients-stats', [DashboardController::class, 'clientsStats']);
                Route::get('/revenue-stats', [DashboardController::class, 'revenueStats']);
                Route::get('/activity', [DashboardController::class, 'activity']);
            });
        });

        Route::get('/queue', [QueueController::class, 'index']);
    });
});
