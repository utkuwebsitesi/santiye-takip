<?php

use App\Http\Controllers\Api\MobileApiController;
use App\Http\Controllers\Api\MobileAuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/mobile')->group(function (): void {
    Route::get('/auth/challenge', [MobileAuthController::class, 'challenge'])->middleware('throttle:20,1');
    Route::post('/auth/login', [MobileAuthController::class, 'login'])->middleware('throttle:5,1');

    Route::middleware('mobile.auth')->group(function (): void {
        Route::post('/auth/logout', [MobileAuthController::class, 'logout']);
        Route::get('/auth/me', [MobileAuthController::class, 'me']);

        Route::get('/bootstrap', [MobileApiController::class, 'bootstrap']);
        Route::get('/dashboard', [MobileApiController::class, 'dashboard']);
        Route::get('/lookups', [MobileApiController::class, 'lookups']);

        Route::get('/transactions', [MobileApiController::class, 'transactions']);
        Route::post('/transactions', [MobileApiController::class, 'storeTransaction']);
        Route::get('/fuel', [MobileApiController::class, 'fuel']);
        Route::post('/fuel', [MobileApiController::class, 'storeFuel']);
        Route::get('/tankers', [MobileApiController::class, 'tankers']);
        Route::get('/tanker-movements', [MobileApiController::class, 'tankerMovements']);
        Route::post('/tanker-purchases', [MobileApiController::class, 'storeTankerPurchase']);
        Route::get('/vehicles', [MobileApiController::class, 'vehicles']);
        Route::get('/maintenance', [MobileApiController::class, 'maintenance']);
        Route::post('/maintenance', [MobileApiController::class, 'storeMaintenance']);
        Route::get('/reports', [MobileApiController::class, 'reports']);
        Route::get('/notifications', [MobileApiController::class, 'notifications']);
        Route::post('/notifications/read-all', [MobileApiController::class, 'readAllNotifications']);
        Route::put('/password', [MobileApiController::class, 'changePassword']);

        Route::middleware('mobile.admin')->group(function (): void {
            Route::patch('/transactions/{transaction}', [MobileApiController::class, 'updateTransaction']);
            Route::delete('/transactions/{transaction}', [MobileApiController::class, 'destroyTransaction']);
            Route::post('/tankers', [MobileApiController::class, 'storeTanker']);
            Route::patch('/tankers/{tanker}', [MobileApiController::class, 'updateTanker']);
            Route::delete('/tankers/{tanker}', [MobileApiController::class, 'destroyTanker']);
            Route::post('/vehicles', [MobileApiController::class, 'storeVehicle']);
            Route::patch('/vehicles/{vehicle}', [MobileApiController::class, 'updateVehicle']);
            Route::delete('/vehicles/{vehicle}', [MobileApiController::class, 'destroyVehicle']);
            Route::patch('/fuel/{fuelEntry}', [MobileApiController::class, 'updateFuel']);
            Route::delete('/fuel/{fuelEntry}', [MobileApiController::class, 'destroyFuel']);
            Route::patch('/maintenance/{maintenance}', [MobileApiController::class, 'updateMaintenance']);
            Route::delete('/maintenance/{maintenance}', [MobileApiController::class, 'destroyMaintenance']);
            Route::get('/users', [MobileApiController::class, 'users']);
            Route::post('/users', [MobileApiController::class, 'storeUser']);
            Route::patch('/users/{user}', [MobileApiController::class, 'updateUser']);
            Route::get('/audit', [MobileApiController::class, 'audit']);
        });

        Route::middleware('mobile.superadmin')->group(function (): void {
            Route::get('/system', [MobileApiController::class, 'system']);
            Route::put('/system/settings', [MobileApiController::class, 'updateSettings']);
            Route::post('/system/categories', [MobileApiController::class, 'storeCategory']);
            Route::patch('/system/categories/{category}', [MobileApiController::class, 'updateCategory']);
            Route::post('/system/backups', [MobileApiController::class, 'createBackup']);
        });
    });
});
