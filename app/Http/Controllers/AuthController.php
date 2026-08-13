<?php

namespace App\Http\Controllers;

use App\Services\LoginCaptcha;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(Request $request, LoginCaptcha $captcha): View
    {
        $challenge = $captcha->issue();

        return view('auth.login', [
            'captchaQuestion' => $challenge['question'],
            'captchaToken' => $challenge['token'],
        ]);
    }

    public function store(Request $request, LoginCaptcha $captcha): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string'],
            'captcha' => ['required', 'string', 'max:10'],
            'captcha_token' => ['required', 'string', 'max:2048'],
        ]);
        $captchaAnswer = $credentials['captcha'];
        $captchaToken = $credentials['captcha_token'];
        unset($credentials['captcha'], $credentials['captcha_token']);

        if (! $captcha->verifyAndConsume($captchaToken, $captchaAnswer)) {
            throw ValidationException::withMessages([
                'captcha' => 'Doğrulama cevabı hatalı veya süresi dolmuş. Lütfen tekrar deneyin.',
            ]);
        }

        if (! Auth::attempt([...$credentials, 'is_active' => true], false)) {
            return back()->withErrors(['username' => 'Kullanıcı adı veya şifre hatalı.'])->onlyInput('username');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
