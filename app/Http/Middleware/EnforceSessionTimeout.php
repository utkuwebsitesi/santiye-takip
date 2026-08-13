<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceSessionTimeout
{
    private const LAST_ACTIVITY_KEY = 'auth_last_activity';

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return $next($request);
        }

        $lastActivity = $request->session()->get(self::LAST_ACTIVITY_KEY);
        $idleSeconds = max(1, (int) config('session.idle_timeout', 15)) * 60;
        $expired = is_numeric($lastActivity) && now()->timestamp - (int) $lastActivity >= $idleSeconds;

        if (Auth::viaRemember() || $expired) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'username' => $expired
                    ? 'Güvenliğiniz için uzun süre işlem yapılmayan oturum kapatıldı.'
                    : 'Kalıcı oturumlar devre dışı bırakıldı. Lütfen yeniden giriş yapın.',
            ]);
        }

        $request->session()->put(self::LAST_ACTIVITY_KEY, now()->timestamp);

        return $next($request);
    }
}
