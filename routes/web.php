<?php

use App\Http\Controllers\BookingController;
use Illuminate\Support\Facades\Route;

// Bookings
Route::post('/bookings/session', [BookingController::class, 'createSession'])->name('bookings.session');
Route::get('/bookings/complete', [BookingController::class, 'complete'])->name('bookings.complete');

// Stripe webhook (CSRF excluded via bootstrap/app.php middleware group if needed)
Route::post('/stripe/webhook', [BookingController::class, 'webhook'])->name('stripe.webhook');
