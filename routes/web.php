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
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('/kasa-hareketleri', [TransactionController::class, 'index'])->name('transactions.index');
    Route::get('/kasa-hareketleri/yeni', [TransactionController::class, 'create'])->name('transactions.create');
    Route::post('/kasa-hareketleri', [TransactionController::class, 'store'])->name('transactions.store');

    Route::get('/yakit', [FuelEntryController::class, 'index'])->name('fuel.index');
    Route::get('/yakit/yeni', [FuelEntryController::class, 'create'])->name('fuel.create');
    Route::post('/yakit', [FuelEntryController::class, 'store'])->name('fuel.store');
    Route::get('/tankerler', [TankerController::class, 'index'])->name('tankers.index');
    Route::get('/tankerler/yakit-alimi', [TankerController::class, 'create'])->name('tankers.purchase.create');
    Route::post('/tankerler/yakit-alimi', [TankerController::class, 'store'])->name('tankers.purchase.store');
    Route::get('/bakim', [MaintenanceController::class, 'index'])->name('maintenance.index');
    Route::get('/bakim/yeni', [MaintenanceController::class, 'create'])->name('maintenance.create');
    Route::post('/bakim', [MaintenanceController::class, 'store'])->name('maintenance.store');
    Route::get('/belgeler/kasa/{transaction}', [DocumentController::class, 'transaction'])->name('documents.transaction');
    Route::get('/belgeler/yakit/{fuelEntry}', [DocumentController::class, 'fuel'])->name('documents.fuel');
    Route::get('/belgeler/bakim/{maintenance}', [DocumentController::class, 'maintenance'])->name('documents.maintenance');
    Route::get('/raporlar', ReportController::class)->name('reports.index');
    Route::get('/bildirimler', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/bildirimler/{notification}/ac', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('/bildirimler/okundu', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('/parola', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('/parola', [PasswordController::class, 'update'])->name('password.update');

    Route::middleware('admin')->group(function (): void {
        Route::get('/tankerler/yonetim', [TankerController::class, 'manage'])->name('tankers.manage');
        Route::post('/tankerler', [TankerController::class, 'storeTanker'])->name('tankers.store');
        Route::patch('/tankerler/{tanker}', [TankerController::class, 'updateTanker'])->name('tankers.update');
        Route::delete('/tankerler/{tanker}/arsivi-temizle', [TankerController::class, 'purgeArchivedAndDestroyTanker'])->name('tankers.purge');
        Route::delete('/tankerler/{tanker}', [TankerController::class, 'destroyTanker'])->name('tankers.destroy');
        Route::get('/kasa-hareketleri/{transaction}/duzenle', [TransactionController::class, 'edit'])->name('transactions.edit');
        Route::patch('/kasa-hareketleri/{transaction}', [TransactionController::class, 'update'])->name('transactions.update');
        Route::delete('/kasa-hareketleri/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');
        Route::get('/yakit/{fuelEntry}/duzenle', [FuelEntryController::class, 'edit'])->name('fuel.edit');
        Route::patch('/yakit/{fuelEntry}', [FuelEntryController::class, 'update'])->name('fuel.update');
        Route::delete('/yakit/{fuelEntry}', [FuelEntryController::class, 'destroy'])->name('fuel.destroy');
        Route::get('/bakim/{maintenance}/duzenle', [MaintenanceController::class, 'edit'])->name('maintenance.edit');
        Route::patch('/bakim/{maintenance}', [MaintenanceController::class, 'update'])->name('maintenance.update');
        Route::delete('/bakim/{maintenance}', [MaintenanceController::class, 'destroy'])->name('maintenance.destroy');
        Route::resource('/araclar', VehicleController::class)->except(['show'])->parameters(['araclar' => 'vehicle']);
        Route::get('/islem-gecmisi', AuditLogController::class)->name('audit.index');
        Route::resource('/kullanicilar', UserController::class)->except(['show', 'destroy'])->parameters(['kullanicilar' => 'user'])->names('users');
    });

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
