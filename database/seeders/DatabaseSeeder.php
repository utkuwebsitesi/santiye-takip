<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $password = (string) env('ADMIN_PASSWORD', '');
        if ($password === '' && app()->environment(['local', 'testing'])) {
            $password = 'Local-Admin-2026!';
        }

        if (! $this->strong($password)) {
            throw new \RuntimeException(
                'ADMIN_PASSWORD eksik veya yetersiz. En az 12 karakter, büyük/küçük harf ve rakam kullanın.'
            );
        }

        User::query()->firstOrCreate(
            ['username' => env('ADMIN_USERNAME', 'admin')],
            [
                'name' => env('ADMIN_NAME', 'Sistem Yöneticisi'),
                'password' => Hash::make($password),
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        $systemPassword = (string) env('SYSTEM_ADMIN_PASSWORD', '');
        if ($systemPassword !== '') {
            if (! $this->strong($systemPassword)) {
                throw new \RuntimeException('SYSTEM_ADMIN_PASSWORD güvenlik şartlarını karşılamıyor.');
            }
            User::query()->updateOrCreate(
                ['username' => env('SYSTEM_ADMIN_USERNAME', 'system')],
                [
                    'name' => env('SYSTEM_ADMIN_NAME', 'Sistem Sahibi'),
                    'password' => Hash::make($systemPassword),
                    'role' => 'super_admin',
                    'is_active' => true,
                ]
            );
        }
    }

    private function strong(string $password): bool
    {
        return strlen($password) >= 12
            && preg_match('/[a-z]/', $password)
            && preg_match('/[A-Z]/', $password)
            && preg_match('/[0-9]/', $password);
    }
}
