<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\MySqlConnection;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

config([
    'database.default' => 'mysql',
    'database.connections.mysql' => [
        'driver' => 'manual-mysql',
        'database' => 'santiye360',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'prefix_indexes' => true,
        'strict' => true,
        'engine' => null,
    ],
]);

DB::extend('manual-mysql', static fn (array $config, string $name): MySqlConnection => new MySqlConnection(
    new PDO('sqlite::memory:'),
    $config['database'],
    $config['prefix'],
    $config
));
DB::purge('mysql');
$connection = DB::connection('mysql');
$queries = $connection->pretend(function (): void {
    foreach (glob(__DIR__.'/../database/migrations/*.php') as $migrationFile) {
        $migration = require $migrationFile;
        $migration->up();
    }
});

$quote = static function (mixed $value): string {
    if ($value === null) {
        return 'NULL';
    }

    if ($value instanceof DateTimeInterface) {
        return "'".$value->format('Y-m-d H:i:s')."'";
    }

    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    return "'".str_replace(['\\', "'"], ['\\\\', "''"], (string) $value)."'";
};

$interpolate = static function (string $sql, array $bindings) use ($connection, $quote): string {
    foreach ($connection->prepareBindings($bindings) as $binding) {
        $position = strpos($sql, '?');
        if ($position === false) {
            break;
        }
        $sql = substr_replace($sql, $quote($binding), $position, 1);
    }

    return $sql;
};

$migrationNames = array_map(
    static fn (string $path): string => pathinfo($path, PATHINFO_FILENAME),
    glob(__DIR__.'/../database/migrations/*.php')
);
$adminPassword = 'S3!'.rtrim(strtr(base64_encode(random_bytes(12)), '+/', 'AZ'), '=').'a';
$adminPasswordHash = password_hash($adminPassword, PASSWORD_BCRYPT);
$generatedAt = date('Y-m-d H:i:s');

$output = [
    '-- Şantiye Takip manual production database',
    '-- WARNING: Existing Şantiye Takip tables in the selected database are deleted.',
    'SET NAMES utf8mb4;',
    'SET FOREIGN_KEY_CHECKS=0;',
    '',
];

$tables = [
    'system_notifications', 'tanker_movements', 'fuel_entries', 'maintenance_entries',
    'tankers', 'transactions', 'audit_logs', 'vehicles', 'navigation_items',
    'transaction_categories', 'app_settings', 'installation_states', 'sessions',
    'password_reset_tokens', 'cache_locks', 'cache', 'users', 'migrations',
];
foreach ($tables as $table) {
    $output[] = 'DROP TABLE IF EXISTS `'.$table.'`;';
}
$output[] = '';

foreach ($queries as $query) {
    $sql = trim($interpolate($query['query'], $query['bindings']));
    if ($sql !== '' && ! str_starts_with(strtolower(ltrim($sql)), 'select ')) {
        $output[] = rtrim($sql, ';').';';
    }
}

$output[] = '';
$output[] = '-- Initial system administrator. Change this password immediately after login.';
$output[] = 'INSERT INTO `users` (`name`, `username`, `password`, `role`, `is_active`, `created_at`, `updated_at`) VALUES ('
    .$quote('Sistem Yöneticisi').', '.$quote('system').', '.$quote($adminPasswordHash).", 'super_admin', 1, "
    .$quote($generatedAt).', '.$quote($generatedAt).');';
$output[] = '';
$output[] = 'CREATE TABLE IF NOT EXISTS `migrations` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, `migration` VARCHAR(255) NOT NULL, `batch` INT NOT NULL) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;';
foreach ($migrationNames as $migrationName) {
    $output[] = 'INSERT INTO `migrations` (`migration`, `batch`) VALUES ('.$quote($migrationName).', 1);';
}
$output[] = '';
$output[] = '-- The first administrator is created with the one-time activation command in MANUEL-KURULUM.md.';
$output[] = 'SET FOREIGN_KEY_CHECKS=1;';
$output[] = '';

$destination = $argv[1] ?? (__DIR__.'/../dist/manual-install/santiye360.sql');
$directory = dirname($destination);
if (! is_dir($directory)) {
    mkdir($directory, 0775, true);
}

file_put_contents($destination, implode(PHP_EOL, $output), LOCK_EX);
$credentials = implode(PHP_EOL, [
    'Şantiye Takip İlk Giriş',
    'Adres: https://360.natex.com.tr/login',
    'Kullanıcı adı: system',
    'Geçici parola: '.$adminPassword,
    '',
    'İlk girişten hemen sonra parolayı değiştirin ve bu dosyayı silin.',
    '',
]);
file_put_contents($directory.'/ILK-GIRIS.txt', $credentials, LOCK_EX);

$appKey = 'base64:'.base64_encode(random_bytes(32));
$environment = implode(PHP_EOL, [
    'APP_NAME="Şantiye Takip"',
    'APP_ENV=production',
    'APP_KEY='.$appKey,
    'APP_DEBUG=false',
    'APP_URL="https://360.natex.com.tr"',
    'APP_TIMEZONE=Europe/Istanbul',
    'APP_LOCALE=tr',
    'APP_FALLBACK_LOCALE=tr',
    '',
    'LOG_CHANNEL=stack',
    'LOG_LEVEL=warning',
    '',
    'DB_CONNECTION=mysql',
    'DB_HOST="localhost"',
    'DB_PORT=3306',
    'DB_DATABASE=""',
    'DB_USERNAME=""',
    'DB_PASSWORD=""',
    '',
    'SESSION_DRIVER=database',
    'SESSION_LIFETIME=15',
    'SESSION_IDLE_TIMEOUT=15',
    'SESSION_EXPIRE_ON_CLOSE=true',
    'SESSION_ENCRYPT=true',
    'SESSION_SECURE_COOKIE=true',
    'SESSION_SAME_SITE=lax',
    '',
    'CACHE_STORE=database',
    'QUEUE_CONNECTION=database',
    'FILESYSTEM_DISK=public',
    '',
]);
file_put_contents($directory.'/.env.manual', $environment, LOCK_EX);
file_put_contents($directory.'/installed.lock', date(DATE_ATOM), LOCK_EX);

$instructions = <<<'MARKDOWN'
# Şantiye Takip Manuel Canlı Kurulum

1. Uygulama üretim ZIP'ini `/360.natex.com.tr/` içinde açın.
2. cPanel MySQL Veritabanları ekranından boş veritabanı ve kullanıcı oluşturup kullanıcıya tüm yetkileri verin.
3. phpMyAdmin'de yalnızca Şantiye Takip için ayırdığınız veritabanını seçip `santiye-takip.sql` dosyasını içe aktarın. SQL önce yarım kalmış Şantiye Takip tablolarını temizler.
4. `.env.manual` dosyasını düzenleyin. `DB_DATABASE`, `DB_USERNAME` ve `DB_PASSWORD` alanlarını doldurun.
5. Düzenlenen `.env.manual` dosyasını uygulama köküne `santiye-kasa/.env` adıyla yükleyin.
6. `installed.lock` dosyasını `santiye-kasa/storage/app/installed.lock` konumuna yükleyin.
7. Subdomain belge kökünün `/360.natex.com.tr/santiye-kasa/public` olduğunu doğrulayın.
8. `ILK-GIRIS.txt` bilgileriyle giriş yapıp parolayı hemen değiştirin.
9. Sunucudan `ILK-GIRIS.txt`, `santiye360.sql` ve yüklediğiniz yardımcı dosyaları kaldırın.

Not: Parolada çift tırnak veya ters eğik çizgi varsa `.env` içinde bunları sırasıyla `\"` ve `\\` olarak yazın.
MARKDOWN;
file_put_contents($directory.'/MANUEL-KURULUM.md', $instructions.PHP_EOL, LOCK_EX);
fwrite(STDOUT, $destination.PHP_EOL);
