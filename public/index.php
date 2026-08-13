<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$environmentPath = __DIR__.'/../.env';
$installationLockPath = __DIR__.'/../storage/app/installed.lock';

if (! file_exists($installationLockPath)) {
    foreach ([
        'SESSION_DRIVER' => 'file',
        'CACHE_STORE' => 'file',
        'QUEUE_CONNECTION' => 'sync',
    ] as $name => $value) {
        putenv($name.'='.$value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

if (! file_exists($environmentPath) && empty($_ENV['APP_KEY']) && empty($_SERVER['APP_KEY']) && getenv('APP_KEY') === false) {
    $installerKeyPath = __DIR__.'/../storage/app/.installer-key';
    if (! file_exists($installerKeyPath)) {
        $installerKey = 'base64:'.base64_encode(random_bytes(32));
        if (file_put_contents($installerKeyPath, $installerKey, LOCK_EX) === false) {
            http_response_code(500);
            exit('Kurulum anahtarı oluşturulamadı. storage/app klasörünün yazma iznini kontrol edin.');
        }
        @chmod($installerKeyPath, 0600);
    }
    $installerKey = trim((string) file_get_contents($installerKeyPath));
    putenv('APP_KEY='.$installerKey);
    $_ENV['APP_KEY'] = $installerKey;
    $_SERVER['APP_KEY'] = $installerKey;
}

if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../vendor/autoload.php';

(require_once __DIR__.'/../bootstrap/app.php')
    ->handleRequest(Request::capture());
