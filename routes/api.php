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

        Route::get('/bootstrap', [MobileApiController::class, 'bootstrap'])->middleware('mobile.permission:dashboard.view');
        Route::get('/dashboard', [MobileApiController::class, 'dashboard'])->middleware('mobile.permission:dashboard.view');
        Route::get('/lookups', [MobileApiController::class, 'lookups']);

        Route::get('/transactions', [MobileApiController::class, 'transactions'])->middleware('mobile.permission:transactions.view');
        Route::post('/transactions', [MobileApiController::class, 'storeTransaction'])->middleware('mobile.permission:transactions.create');
        Route::get('/fuel', [MobileApiController::class, 'fuel'])->middleware('mobile.permission:fuel.view');
        Route::post('/fuel', [MobileApiController::class, 'storeFuel'])->middleware('mobile.permission:fuel.create');
        Route::get('/tankers', [MobileApiController::class, 'tankers'])->middleware('mobile.permission:tankers.view');
        Route::get('/tanker-movements', [MobileApiController::class, 'tankerMovements'])->middleware('mobile.permission:tankers.view');
        Route::post('/tanker-purchases', [MobileApiController::class, 'storeTankerPurchase'])->middleware('mobile.permission:tankers.purchase');
        Route::get('/vehicles', [MobileApiController::class, 'vehicles'])->middleware('mobile.permission:vehicles.view');
        Route::get('/maintenance', [MobileApiController::class, 'maintenance'])->middleware('mobile.permission:maintenance.view');
        Route::post('/maintenance', [MobileApiController::class, 'storeMaintenance'])->middleware('mobile.permission:maintenance.create');
        Route::get('/reports', [MobileApiController::class, 'reports'])->middleware('mobile.permission:reports.view');
        Route::get('/notifications', [MobileApiController::class, 'notifications'])->middleware('mobile.permission:notifications.view');
        Route::post('/notifications/read-all', [MobileApiController::class, 'readAllNotifications'])->middleware('mobile.permission:notifications.view');
        Route::put('/password', [MobileApiController::class, 'changePassword']);

        Route::patch('/transactions/{transaction}', [MobileApiController::class, 'updateTransaction'])->middleware('mobile.permission:transactions.manage');
        Route::delete('/transactions/{transaction}', [MobileApiController::class, 'destroyTransaction'])->middleware('mobile.permission:transactions.manage');
        Route::post('/tankers', [MobileApiController::class, 'storeTanker'])->middleware('mobile.permission:tankers.manage');
        Route::patch('/tankers/{tanker}', [MobileApiController::class, 'updateTanker'])->middleware('mobile.permission:tankers.manage');
        Route::delete('/tankers/{tanker}', [MobileApiController::class, 'destroyTanker'])->middleware('mobile.permission:tankers.manage');
        Route::post('/vehicles', [MobileApiController::class, 'storeVehicle'])->middleware('mobile.permission:vehicles.manage');
        Route::patch('/vehicles/{vehicle}', [MobileApiController::class, 'updateVehicle'])->middleware('mobile.permission:vehicles.manage');
        Route::delete('/vehicles/{vehicle}', [MobileApiController::class, 'destroyVehicle'])->middleware('mobile.permission:vehicles.manage');
        Route::patch('/fuel/{fuelEntry}', [MobileApiController::class, 'updateFuel'])->middleware('mobile.permission:fuel.manage');
        Route::delete('/fuel/{fuelEntry}', [MobileApiController::class, 'destroyFuel'])->middleware('mobile.permission:fuel.manage');
        Route::patch('/maintenance/{maintenance}', [MobileApiController::class, 'updateMaintenance'])->middleware('mobile.permission:maintenance.manage');
        Route::delete('/maintenance/{maintenance}', [MobileApiController::class, 'destroyMaintenance'])->middleware('mobile.permission:maintenance.manage');
        Route::get('/users', [MobileApiController::class, 'users'])->middleware(['mobile.admin', 'mobile.permission:users.manage']);
        Route::post('/users', [MobileApiController::class, 'storeUser'])->middleware(['mobile.admin', 'mobile.permission:users.manage']);
        Route::patch('/users/{user}', [MobileApiController::class, 'updateUser'])->middleware(['mobile.admin', 'mobile.permission:users.manage']);
        Route::get('/audit', [MobileApiController::class, 'audit'])->middleware('mobile.permission:audit.view');

        Route::middleware('mobile.superadmin')->group(function (): void {
            Route::get('/system', [MobileApiController::class, 'system']);
            Route::put('/system/settings', [MobileApiController::class, 'updateSettings']);
            Route::post('/system/categories', [MobileApiController::class, 'storeCategory']);
            Route::patch('/system/categories/{category}', [MobileApiController::class, 'updateCategory']);
            Route::post('/system/backups', [MobileApiController::class, 'createBackup']);
        });
    });
});
