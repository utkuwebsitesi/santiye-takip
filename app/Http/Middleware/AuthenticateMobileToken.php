<?php

namespace App\Http\Middleware;

use App\Models\MobileAccessToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMobileToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = trim((string) $request->bearerToken());
        if ($token === '' || ! str_starts_with($token, 'st_')) {
            return response()->json(['message' => 'Mobil oturum anahtarı gerekli.'], 401);
        }

        $accessToken = MobileAccessToken::query()
            ->with('user')
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if (! $accessToken || ! $accessToken->isUsable()) {
            if ($accessToken && $accessToken->revoked_at === null) {
                $accessToken->forceFill(['revoked_at' => now()])->save();
            }

            return response()->json([
                'message' => 'Oturumunuz sona erdi. Güvenliğiniz için yeniden giriş yapın.',
                'code' => 'mobile_session_expired',
            ], 401);
        }

        $accessToken->markUsed();
        Auth::setUser($accessToken->user);
        $request->setUserResolver(fn () => $accessToken->user);
        $request->attributes->set('mobile_access_token', $accessToken);

        return $next($request);
    }
}
