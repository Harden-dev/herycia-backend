<?php

use App\Http\Controllers\API\Booking\AppointmentTrackingController;
use App\Http\Controllers\API\Booking\BookingController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/api/v1.php';

Route::prefix('booking')->group(function () {
    Route::get('/{slug}', [BookingController::class, 'show']);
    Route::post('/{slug}', [BookingController::class, 'store']);
});

Route::get('/rdv/{tracking_token}', [AppointmentTrackingController::class, 'show']);
