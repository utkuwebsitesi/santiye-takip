<?php

return [
    'enabled' => env('BACKUP_ENABLED', true),
    'mysqldump_path' => env('MYSQLDUMP_PATH', 'mysqldump'),
    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 14),
    'directory' => storage_path('app/private/backups'),
];
