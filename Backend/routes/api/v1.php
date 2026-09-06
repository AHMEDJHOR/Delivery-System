<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\MenuItemController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\RestaurantController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V1 Routes
|--------------------------------------------------------------------------
|
| Routes for API version 1.
|
*/

// Health check
Route::get('health', fn () => response()->json([
    'status' => 'healthy',
    'timestamp' => now()->toDateTimeString(),
]))->name('api.v1.health');

// Public routes with auth rate limiter (5/min - brute force protection)
Route::middleware('throttle:auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register'])->name('api.v1.register');
    Route::post('login', [AuthController::class, 'login'])->name('api.v1.login');
});

// Email verification
Route::get('email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware('signed')
    ->name('verification.verify');

    // Public restaurant routes
Route::get('restaurants', [RestaurantController::class, 'index'])
    ->name('api.v1.restaurants.index');

Route::get('restaurants/{restaurant}', [RestaurantController::class, 'show'])
    ->name('api.v1.restaurants.show');

Route::get('restaurants/{restaurant}/menu-items', [RestaurantController::class, 'menuItems'])
    ->name('api.v1.restaurants.menu-items');

// Public menu item routes
Route::get('menu-items', [MenuItemController::class, 'index'])
    ->name('api.v1.menu-items.index');

Route::get('menu-items/{menuItem}', [MenuItemController::class, 'show'])
    ->name('api.v1.menu-items.show');

// Protected routes with authenticated rate limiter (120/min)
Route::middleware(['auth:sanctum', 'throttle:authenticated'])->group(function (): void {
    Route::post('logout', [AuthController::class, 'logout'])->name('api.v1.logout');
    Route::get('profile', [AuthController::class, 'profile'])->name('api.v1.profile');
    // Change password
    Route::put('change-password', [AuthController::class, 'changePassword'])->name('api.v1.change-password');

    Route::post('email/resend', [AuthController::class, 'resendVerificationEmail'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
        // Customer Cart routes
    Route::middleware('role:customer')->group(function (): void {
        Route::get('cart', [CartController::class, 'index'])
            ->name('api.v1.cart.index');

        Route::post('cart', [CartController::class, 'store'])
            ->name('api.v1.cart.store');

        Route::put('cart/{cartItem}', [CartController::class, 'update'])
            ->name('api.v1.cart.update');

        Route::delete('cart', [CartController::class, 'clear'])
            ->name('api.v1.cart.clear');

        Route::delete('cart/{cartItem}', [CartController::class, 'destroy'])
            ->name('api.v1.cart.destroy');
    });

    Route::get('orders', [OrderController::class, 'index'])->name('api.v1.orders.index');

        // Restaurant Manager routes
    Route::middleware('role:restaurant_manager')->group(function (): void {

        Route::post('restaurants', [RestaurantController::class, 'store'])
            ->name('api.v1.restaurants.store');

        Route::put('restaurants/{restaurant}', [RestaurantController::class, 'update'])
            ->name('api.v1.restaurants.update');

        Route::get('my-restaurants', [RestaurantController::class, 'myRestaurants'])
            ->name('api.v1.restaurants.my');

    // Menu Item routes
    Route::post('restaurants/{restaurant}/menu-items', [MenuItemController::class, 'store'])
        ->name('api.v1.restaurants.menu-items.store');

    Route::put('menu-items/{menuItem}', [MenuItemController::class, 'update'])
        ->name('api.v1.menu-items.update');

    Route::delete('menu-items/{menuItem}', [MenuItemController::class, 'destroy'])
        ->name('api.v1.menu-items.destroy');
    });

    // Admin restaurant routes
    Route::middleware('role:admin')->group(function (): void {

        Route::patch(
            'restaurants/{restaurant}/approval-status',
            [RestaurantController::class, 'updateApprovalStatus']
        )->name('api.v1.restaurants.approval-status');

        Route::delete('restaurants/{restaurant}', [RestaurantController::class, 'destroy'])
            ->name('api.v1.restaurants.destroy');
    });
});

// Password reset routes (public with rate limiting)
Route::middleware('throttle:6,1')->group(function (): void {
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])
        ->name('password.email');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])
        ->name('password.reset');
});