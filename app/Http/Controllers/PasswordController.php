<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function edit(): View
    {
        return view('auth.password');
    }

    public function update(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->mixedCase()->numbers()],
        ], [
            'current_password.current_password' => 'Mevcut parola yanlış.',
            'password.confirmed' => 'Yeni parola tekrarı eşleşmiyor.',
        ]);

        $user = $request->user();
        $user->password = Hash::make($data['password']);
        $user->save();
        $audit->event($user, 'password_changed', null, ['user_id' => $user->id], 'Kullanıcı kendi parolasını değiştirdi.');
        $request->session()->regenerate();

        return back()->with('success', 'Parolanız güvenli biçimde değiştirildi.');
    }
}
