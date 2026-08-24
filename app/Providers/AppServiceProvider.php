<?php

namespace App\Providers;

use App\Models\AppSetting;
use App\Models\NavigationItem;
use App\Models\SystemNotification;
use App\Services\HeaderBriefingService;
use App\Services\MaintenanceReminderService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // cPanel hosts using older InnoDB row formats cap indexed keys at 767/1000 bytes.
        Schema::defaultStringLength(191);
        Paginator::useBootstrapFive();

        View::composer('layouts.app', function ($view): void {
            $brand = ['software_name' => 'Şantiye Takip', 'software_tagline' => 'Kasa & Yakıt Yönetimi', 'company_name' => ''];
            $navigation = collect();
            $maintenanceAlerts = collect();
            $headerNotifications = collect();
            $newNotifications = collect();
            $unreadNotificationCount = 0;
            $headerBriefing = ['weather' => [], 'rates' => [], 'weather_updated_at' => null, 'rates_updated_at' => null];
            try {
                if (Schema::hasTable('app_settings')) {
                    $brand = array_merge($brand, AppSetting::pluck('value', 'key')->all());
                    if (($brand['software_name'] ?? null) === 'Şantiye360') {
                        $brand['software_name'] = 'Şantiye Takip';
                    }
                }
                if (auth()->check() && Schema::hasTable('navigation_items')) {
                    $routes = [
                        'dashboard' => ['dashboard', 'dashboard'],
                        'transaction_create' => ['transactions.create', 'transactions.create'],
                        'transactions' => ['transactions.index', 'transactions.index'],
                        'fuel_report' => ['fuel.index', 'fuel.*'],
                        'tankers' => ['tankers.index', 'tankers.*'],
                        'maintenance' => ['maintenance.index', 'maintenance.*'],
                        'reports' => ['reports.index', 'reports.*'],
                        'vehicles' => ['araclar.index', 'araclar.*'],
                        'audit' => ['audit.index', 'audit.*'],
                        'users' => ['users.index', 'users.*'],
                        'system_management' => ['system.index', 'system.*'],
                    ];
                    $navigationPermissions = [
                        'dashboard' => 'dashboard.view', 'transaction_create' => 'transactions.create',
                        'transactions' => 'transactions.view', 'fuel_report' => 'fuel.view',
                        'tankers' => 'tankers.view', 'maintenance' => 'maintenance.view',
                        'reports' => 'reports.view', 'vehicles' => 'vehicles.view',
                        'audit' => 'audit.view', 'users' => 'users.manage',
                        'system_management' => 'system.manage',
                    ];
                    $user = auth()->user();
                    $navigation = NavigationItem::where('is_enabled', true)->orderBy('sort_order')->get()
                        ->filter(fn ($item) => $user->hasPermission($navigationPermissions[$item->key] ?? 'dashboard.view'))
                        ->filter(fn ($item) => isset($routes[$item->key]))
                        ->map(function ($item) use ($routes) {
                            [$route, $pattern] = $routes[$item->key];
                            $item->route_name = $route;
                            $item->route_pattern = $pattern;

                            return $item;
                        })->values();
                }
                if (auth()->check() && Schema::hasTable('maintenance_entries') && Schema::hasColumn('fuel_entries', 'operating_hours')) {
                    $maintenanceAlerts = app(MaintenanceReminderService::class)->due();
                }
                if (auth()->check() && Schema::hasTable('system_notifications')) {
                    foreach ($maintenanceAlerts as $alert) {
                        $notification = SystemNotification::firstOrCreate(
                            [
                                'user_id' => auth()->id(),
                                'maintenance_entry_id' => $alert->id,
                            ],
                            [
                                'title' => 'Bakım zamanı geldi: '.($alert->vehicle->plate ?? $alert->vehicle->code),
                                'message' => $alert->maintenance_type.' · '.$alert->reminder_reasons->join(' · '),
                                'link' => route('maintenance.index'),
                            ]
                        );
                        if ($notification->wasRecentlyCreated) {
                            $newNotifications->push($notification);
                        }
                    }
                    $headerNotifications = SystemNotification::where('user_id', auth()->id())->latest()->limit(8)->get();
                    $unreadNotificationCount = SystemNotification::where('user_id', auth()->id())->whereNull('read_at')->count();
                }
                if (auth()->check()) {
                    $headerBriefing = app(HeaderBriefingService::class)->get();
                }
            } catch (Throwable) {
                // Installer çalışırken veritabanı henüz hazır olmayabilir.
            }
            $view->with(compact(
                'brand', 'navigation', 'maintenanceAlerts', 'headerNotifications',
                'newNotifications', 'unreadNotificationCount', 'headerBriefing'
            ));
        });
    }
}
