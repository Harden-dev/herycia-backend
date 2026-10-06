<?php

use App\Http\Controllers\API\Booking\AppointmentTrackingController;
use App\Http\Controllers\API\Booking\BookingController;
use App\Http\Controllers\API\Booking\QueueCheckInController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/api/v1.php';

Route::prefix('booking')->group(function () {
    Route::get('/{slug}', [BookingController::class, 'show'])->middleware('throttle:public-read');
    Route::post('/{slug}', [BookingController::class, 'store'])->middleware('throttle:public-booking');
});

Route::get('/rdv/{tracking_token}', [AppointmentTrackingController::class, 'show'])->middleware('throttle:public-read');

// File d'attente : arrivée au salon (QR d'accueil) et suivi de la position
Route::prefix('checkin/{slug}')->middleware('throttle:public-checkin')->group(function () {
    Route::post('/', [QueueCheckInController::class, 'store']);
    Route::post('/late-choice', [QueueCheckInController::class, 'lateChoice']);
    Route::get('/walk-in', [QueueCheckInController::class, 'walkInOptions']);
    Route::post('/walk-in', [QueueCheckInController::class, 'walkIn']);
});
Route::get('/file/{token}', [QueueCheckInController::class, 'show'])->middleware('throttle:public-read');
