<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InstallController extends Controller
{
    public function index(): View
    {
        $requirements = [
            'PHP 8.3 veya üzeri' => version_compare(PHP_VERSION, '8.3.0', '>='),
            'PDO MySQL' => extension_loaded('pdo_mysql'),
            'Mbstring' => extension_loaded('mbstring'),
            'OpenSSL' => extension_loaded('openssl'),
            'Fileinfo' => extension_loaded('fileinfo'),
            'JSON' => extension_loaded('json'),
            'storage yazılabilir' => is_writable(storage_path()),
            'bootstrap/cache yazılabilir' => is_writable(base_path('bootstrap/cache')),
        ];

        return view('install.index', [
            'requirements' => $requirements,
            'ready' => ! in_array(false, $requirements, true),
            'envExists' => file_exists(base_path('.env')),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'app_name' => ['required', 'string', 'max:100'],
            'app_url' => ['required', 'url', 'max:255'],
            'db_host' => ['required', 'string', 'max:255'],
            'db_port' => ['required', 'integer', 'between:1,65535'],
            'db_database' => ['required', 'string', 'max:100'],
            'db_username' => ['required', 'string', 'max:100'],
            'db_password' => ['nullable', 'string', 'max:255'],
            'admin_name' => ['required', 'string', 'max:150'],
            'admin_username' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'admin_password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()],
            'confirm_env_backup' => [file_exists(base_path('.env')) ? 'accepted' : 'nullable'],
        ]);

        $this->testConnection($data);
        $envPath = base_path('.env');
        $key = 'base64:'.base64_encode(random_bytes(32));

        try {
            if (file_exists($envPath)) {
                $backup = $envPath.'.backup-'.now()->format('Ymd-His');
                throw_unless(copy($envPath, $backup), new \RuntimeException('.env yedeği oluşturulamadı.'));
            }

            // Migrations complete before database-backed cache and sessions are enabled.
            // This keeps the installer reachable when a migration or permission error occurs.
            $bootstrapEnv = $this->environment($data, $key);
            throw_unless(file_put_contents($envPath, $bootstrapEnv, LOCK_EX) !== false, new \RuntimeException('.env yazılamadı.'));

            config([
                'app.key' => $key,
                'app.name' => $data['app_name'],
                'app.url' => $data['app_url'],
                'database.default' => 'mysql',
                'database.connections.mysql.host' => $data['db_host'],
                'database.connections.mysql.port' => $data['db_port'],
                'database.connections.mysql.database' => $data['db_database'],
                'database.connections.mysql.username' => $data['db_username'],
                'database.connections.mysql.password' => $data['db_password'] ?? '',
            ]);
            DB::purge('mysql');
            Artisan::call('migrate', ['--force' => true]);
            DB::transaction(function () use ($data): void {
                User::updateOrCreate(
                    ['username' => mb_strtolower($data['admin_username'], 'UTF-8')],
                    ['name' => $data['admin_name'], 'password' => Hash::make($data['admin_password']), 'role' => 'super_admin', 'is_active' => true]
                );
            });

            $productionEnv = $this->environment($data, $key);
            throw_unless(file_put_contents($envPath, $productionEnv, LOCK_EX) !== false, new \RuntimeException('.env sonlandırılamadı.'));
            file_put_contents(storage_path('app/installed.lock'), now()->toIso8601String(), LOCK_EX);
            $installerKeyPath = storage_path('app/.installer-key');
            if (file_exists($installerKeyPath)) {
                @unlink($installerKeyPath);
            }
        } catch (\Throwable $e) {
            report(new \RuntimeException('Kurulum tamamlanamadı; gizli ayrıntılar güvenlik nedeniyle bastırıldı.', 0, $e));

            return back()->withInput($request->except(['db_password', 'admin_password', 'admin_password_confirmation']))
                ->withErrors(['install' => 'Kurulum tamamlanamadı. Veritabanı yetkilerini ve dizin izinlerini kontrol edin.']);
        }

        return redirect()->route('login')->with('success', 'Kurulum tamamlandı. Yönetici hesabınızla giriş yapabilirsiniz.');
    }

    private function testConnection(array $data): void
    {
        try {
            new \PDO(
                "mysql:host={$data['db_host']};port={$data['db_port']};dbname={$data['db_database']};charset=utf8mb4",
                $data['db_username'],
                $data['db_password'] ?? '',
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'db_database' => 'Veritabanına bağlanılamadı. Sunucu, veritabanı ve kullanıcı yetkilerini kontrol edin.',
            ]);
        }
    }

    private function environment(array $data, string $key): string
    {
        $quote = fn (string $value): string => '"'.str_replace(
            ['\\', '"', '$', "\r", "\n"],
            ['\\\\', '\\"', '\\$', '', ''],
            $value
        ).'"';

        return implode(PHP_EOL, [
            'APP_NAME='.$quote($data['app_name']), 'APP_ENV=production', 'APP_KEY='.$key,
            'APP_DEBUG=false', 'APP_URL='.$quote($data['app_url']), 'APP_TIMEZONE=Europe/Istanbul',
            'APP_LOCALE=tr', 'APP_FALLBACK_LOCALE=tr', '',
            'LOG_CHANNEL=stack', 'LOG_LEVEL=warning', '',
            'DB_CONNECTION=mysql', 'DB_HOST='.$quote($data['db_host']), 'DB_PORT='.$data['db_port'],
            'DB_DATABASE='.$quote($data['db_database']), 'DB_USERNAME='.$quote($data['db_username']),
            'DB_PASSWORD='.$quote($data['db_password'] ?? ''), '',
            'SESSION_DRIVER=file', 'SESSION_LIFETIME=15', 'SESSION_IDLE_TIMEOUT=15',
            'SESSION_EXPIRE_ON_CLOSE=true', 'SESSION_ENCRYPT=true',
            'SESSION_SECURE_COOKIE=true', 'SESSION_SAME_SITE=lax', '',
            'CACHE_STORE=file', 'QUEUE_CONNECTION=sync', 'FILESYSTEM_DISK=public', '',
        ]).PHP_EOL;
    }
}
