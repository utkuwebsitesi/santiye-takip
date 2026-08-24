<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\FuelEntryController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SystemManagementController;
use App\Http\Controllers\TankerController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VehicleController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::get('/health', HealthController::class)
    ->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, ValidateCsrfToken::class])
    ->name('health');

Route::middleware('installation.open')->group(function (): void {
    Route::get('/install', [InstallController::class, 'index'])->name('install.index');
    Route::post('/install', [InstallController::class, 'store'])->name('install.store');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/giris', [AuthController::class, 'create'])->name('login');
    Route::post('/giris', [AuthController::class, 'store'])->middleware('throttle:5,1');
});

Route::middleware(['auth', 'active', 'session.timeout'])->group(function (): void {
    Route::post('/cikis', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/', DashboardController::class)->middleware('permission:dashboard.view')->name('dashboard');

    Route::get('/kasa-hareketleri', [TransactionController::class, 'index'])->middleware('permission:transactions.view')->name('transactions.index');
    Route::get('/kasa-hareketleri/yeni', [TransactionController::class, 'create'])->middleware('permission:transactions.create')->name('transactions.create');
    Route::post('/kasa-hareketleri', [TransactionController::class, 'store'])->middleware('permission:transactions.create')->name('transactions.store');

    Route::get('/yakit', [FuelEntryController::class, 'index'])->middleware('permission:fuel.view')->name('fuel.index');
    Route::get('/yakit/yeni', [FuelEntryController::class, 'create'])->middleware('permission:fuel.create')->name('fuel.create');
    Route::post('/yakit', [FuelEntryController::class, 'store'])->middleware('permission:fuel.create')->name('fuel.store');
    Route::get('/tankerler', [TankerController::class, 'index'])->middleware('permission:tankers.view')->name('tankers.index');
    Route::get('/tankerler/yakit-alimi', [TankerController::class, 'create'])->middleware('permission:tankers.purchase')->name('tankers.purchase.create');
    Route::post('/tankerler/yakit-alimi', [TankerController::class, 'store'])->middleware('permission:tankers.purchase')->name('tankers.purchase.store');
    Route::get('/bakim', [MaintenanceController::class, 'index'])->middleware('permission:maintenance.view')->name('maintenance.index');
    Route::get('/bakim/yeni', [MaintenanceController::class, 'create'])->middleware('permission:maintenance.create')->name('maintenance.create');
    Route::post('/bakim', [MaintenanceController::class, 'store'])->middleware('permission:maintenance.create')->name('maintenance.store');
    Route::get('/belgeler/kasa/{transaction}', [DocumentController::class, 'transaction'])->middleware('permission:transactions.view')->name('documents.transaction');
    Route::get('/belgeler/yakit/{fuelEntry}', [DocumentController::class, 'fuel'])->middleware('permission:fuel.view')->name('documents.fuel');
    Route::get('/belgeler/bakim/{maintenance}', [DocumentController::class, 'maintenance'])->middleware('permission:maintenance.view')->name('documents.maintenance');
    Route::get('/raporlar', ReportController::class)->middleware('permission:reports.view')->name('reports.index');
    Route::get('/bildirimler', [NotificationController::class, 'index'])->middleware('permission:notifications.view')->name('notifications.index');
    Route::get('/bildirimler/{notification}/ac', [NotificationController::class, 'open'])->middleware('permission:notifications.view')->name('notifications.open');
    Route::post('/bildirimler/okundu', [NotificationController::class, 'readAll'])->middleware('permission:notifications.view')->name('notifications.read-all');
    Route::get('/parola', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('/parola', [PasswordController::class, 'update'])->name('password.update');

    Route::get('/tankerler/yonetim', [TankerController::class, 'manage'])->middleware('permission:tankers.manage')->name('tankers.manage');
    Route::post('/tankerler', [TankerController::class, 'storeTanker'])->middleware('permission:tankers.manage')->name('tankers.store');
    Route::patch('/tankerler/{tanker}', [TankerController::class, 'updateTanker'])->middleware('permission:tankers.manage')->name('tankers.update');
    Route::delete('/tankerler/{tanker}/arsivi-temizle', [TankerController::class, 'purgeArchivedAndDestroyTanker'])->middleware('permission:tankers.manage')->name('tankers.purge');
    Route::delete('/tankerler/{tanker}', [TankerController::class, 'destroyTanker'])->middleware('permission:tankers.manage')->name('tankers.destroy');
    Route::get('/kasa-hareketleri/{transaction}/duzenle', [TransactionController::class, 'edit'])->middleware('permission:transactions.manage')->name('transactions.edit');
    Route::patch('/kasa-hareketleri/{transaction}', [TransactionController::class, 'update'])->middleware('permission:transactions.manage')->name('transactions.update');
    Route::delete('/kasa-hareketleri/{transaction}', [TransactionController::class, 'destroy'])->middleware('permission:transactions.manage')->name('transactions.destroy');
    Route::get('/yakit/{fuelEntry}/duzenle', [FuelEntryController::class, 'edit'])->middleware('permission:fuel.manage')->name('fuel.edit');
    Route::patch('/yakit/{fuelEntry}', [FuelEntryController::class, 'update'])->middleware('permission:fuel.manage')->name('fuel.update');
    Route::delete('/yakit/{fuelEntry}', [FuelEntryController::class, 'destroy'])->middleware('permission:fuel.manage')->name('fuel.destroy');
    Route::get('/bakim/{maintenance}/duzenle', [MaintenanceController::class, 'edit'])->middleware('permission:maintenance.manage')->name('maintenance.edit');
    Route::patch('/bakim/{maintenance}', [MaintenanceController::class, 'update'])->middleware('permission:maintenance.manage')->name('maintenance.update');
    Route::delete('/bakim/{maintenance}', [MaintenanceController::class, 'destroy'])->middleware('permission:maintenance.manage')->name('maintenance.destroy');
    Route::get('/araclar', [VehicleController::class, 'index'])->middleware('permission:vehicles.view')->name('araclar.index');
    Route::get('/araclar/yeni', [VehicleController::class, 'create'])->middleware('permission:vehicles.manage')->name('araclar.create');
    Route::post('/araclar', [VehicleController::class, 'store'])->middleware('permission:vehicles.manage')->name('araclar.store');
    Route::get('/araclar/{vehicle}/duzenle', [VehicleController::class, 'edit'])->middleware('permission:vehicles.manage')->name('araclar.edit');
    Route::patch('/araclar/{vehicle}', [VehicleController::class, 'update'])->middleware('permission:vehicles.manage')->name('araclar.update');
    Route::delete('/araclar/{vehicle}', [VehicleController::class, 'destroy'])->middleware('permission:vehicles.manage')->name('araclar.destroy');
    Route::get('/islem-gecmisi', AuditLogController::class)->middleware('permission:audit.view')->name('audit.index');
    Route::resource('/kullanicilar', UserController::class)->except(['show', 'destroy'])->parameters(['kullanicilar' => 'user'])->names('users')->middleware(['admin', 'permission:users.manage']);

    Route::middleware('superadmin')->prefix('yonetim')->name('system.')->group(function (): void {
        Route::get('/', [SystemManagementController::class, 'index'])->name('index');
        Route::put('/ayarlar', [SystemManagementController::class, 'updateSettings'])->name('settings');
        Route::post('/kategoriler', [SystemManagementController::class, 'storeCategory'])->name('categories.store');
        Route::put('/kategoriler/{category}', [SystemManagementController::class, 'updateCategory'])->name('categories.update');
        Route::put('/menu', [SystemManagementController::class, 'updateNavigation'])->name('navigation');
        Route::post('/yedekler', [BackupController::class, 'store'])->name('backups.store');
        Route::get('/yedekler/{filename}', [BackupController::class, 'download'])->name('backups.download');
    });
});
