<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MobileAccessToken;
use App\Models\User;
use App\Services\AuditService;
use App\Services\LoginCaptcha;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class MobileAuthController extends Controller
{
    public function challenge(LoginCaptcha $captcha): JsonResponse
    {
        return response()->json([
            'data' => $captcha->issue(),
            'expires_in_seconds' => max(1, config('mobile.captcha_minutes')) * 60,
        ]);
    }

    public function login(Request $request, LoginCaptcha $captcha, AuditService $audit): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:255'],
            'captcha' => ['required', 'string', 'max:10'],
            'captcha_token' => ['required', 'string', 'max:2048'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        if (! $captcha->verifyAndConsume($data['captcha_token'], $data['captcha'])) {
            throw ValidationException::withMessages([
                'captcha' => 'Güvenlik doğrulaması hatalı veya süresi dolmuş. Yeni soruyu deneyin.',
            ]);
        }

        $user = User::query()->where('username', $data['username'])->first();
        if (! $user || ! $user->is_active || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'username' => 'Kullanıcı adı veya şifre hatalı.',
            ]);
        }

        $issued = MobileAccessToken::issue($user, $data['device_name'] ?? null);
        auth()->setUser($user);
        $audit->event($user, 'mobile_login', null, [
            'device_name' => $issued['model']->device_name,
            'expires_at' => $issued['model']->expires_at->toIso8601String(),
        ], 'Mobil uygulama oturumu açıldı.');

        return response()->json([
            'message' => 'Güvenli giriş yapıldı.',
            'token' => $issued['token'],
            'expires_at' => $issued['model']->expires_at->toIso8601String(),
            'idle_timeout_minutes' => max(1, config('mobile.token_idle_minutes')),
            'user' => $this->userPayload($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->userPayload($request->user()),
            'idle_timeout_minutes' => max(1, config('mobile.token_idle_minutes')),
        ]);
    }

    public function logout(Request $request, AuditService $audit): JsonResponse
    {
        /** @var MobileAccessToken|null $token */
        $token = $request->attributes->get('mobile_access_token');
        if ($token && $token->revoked_at === null) {
            $token->update(['revoked_at' => now()]);
            $audit->event($request->user(), 'mobile_logout', null, ['device_name' => $token->device_name], 'Mobil uygulama oturumu kapatıldı.');
        }

        return response()->json(['message' => 'Oturum kapatıldı.']);
    }

    /** @return array<string, mixed> */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'role' => $user->role,
            'is_admin' => $user->isAdmin(),
            'is_super_admin' => $user->isSuperAdmin(),
        ];
    }
}
