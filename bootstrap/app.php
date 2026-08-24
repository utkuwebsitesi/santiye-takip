<?php

use App\Http\Middleware\AdminOnly;
use App\Http\Middleware\EnforceSessionTimeout;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\InstallationOpen;
use App\Http\Middleware\PreventBrowserCache;
use App\Http\Middleware\PermissionMiddleware;
use App\Http\Middleware\EnsureMobilePermission;
use App\Http\Middleware\SuperAdminOnly;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            PreventBrowserCache::class,
        ]);

        $middleware->alias([
            'admin' => AdminOnly::class,
            'active' => EnsureUserIsActive::class,
            'session.timeout' => EnforceSessionTimeout::class,
            'installation.open' => InstallationOpen::class,
            'superadmin' => SuperAdminOnly::class,
            'permission' => PermissionMiddleware::class,
            'mobile.auth' => \App\Http\Middleware\AuthenticateMobileToken::class,
            'mobile.admin' => \App\Http\Middleware\EnsureMobileAdmin::class,
            'mobile.superadmin' => \App\Http\Middleware\EnsureMobileSuperAdmin::class,
            'mobile.permission' => EnsureMobilePermission::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (QueryException $exception, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Hizmet geçici olarak kullanılamıyor.'], 503);
            }

            return response()->view('errors.service-unavailable', [], 503);
        });
    })->create();
