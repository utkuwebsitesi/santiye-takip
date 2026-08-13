<?php

use App\Services\DatabaseBackupService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('santiye:health', function (): void {
    $this->info('Şantiye Takip uygulaması çalışıyor.');
})->purpose('Uygulama temel sağlık kontrolü');

Artisan::command('santiye:backup', function (DatabaseBackupService $backups): int {
    $path = $backups->create();
    $this->info('Yedek oluşturuldu: '.basename($path));

    return 0;
})->purpose('MySQL veritabanının tutarlı ve sıkıştırılmış yedeğini alır');

Schedule::command('santiye:backup')
    ->dailyAt('02:30')
    ->withoutOverlapping(180)
    ->onOneServer();
