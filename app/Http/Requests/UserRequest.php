<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['username' => mb_strtolower(trim((string) $this->input('username')), 'UTF-8')]);
    }

    public function rules(): array
    {
        $user = $this->route('user');
        $password = ['nullable'];
        if (! $user) {
            $password = ['required'];
        }

        return [
            'name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users')->ignore($user)],
            'role' => ['required', Rule::in($this->user()?->isSuperAdmin() ? ['super_admin', 'admin', 'personnel'] : ['admin', 'personnel'])],
            'is_active' => ['nullable', 'boolean'],
            'password' => [...$password, 'confirmed', Password::min(10)->letters()->mixedCase()->numbers()],
        ];
    }

    public function messages(): array
    {
        return [
            'username.regex' => 'Kullanıcı adı yalnızca küçük harf, rakam, nokta, tire ve alt çizgi içerebilir.',
            'username.unique' => 'Bu kullanıcı adı kullanılmaktadır.',
            'password.confirmed' => 'Parola tekrarı eşleşmiyor.',
            'password.min' => 'Parola en az 10 karakter olmalıdır.',
        ];
    }
}
