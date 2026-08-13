<?php

return [
    'default' => env('FILESYSTEM_DISK', 'local'),
    'disks' => [
        'documents' => [
            'driver' => 'local',
            'root' => storage_path('app/private/documents'),
            'serve' => false,
            'throw' => true,
        ],
        'local' => ['driver' => 'local', 'root' => storage_path('app/private'), 'serve' => true, 'throw' => false],
        'public' => ['driver' => 'local', 'root' => storage_path('app/public'), 'url' => env('APP_URL').'/storage', 'visibility' => 'public', 'throw' => false],
    ],
    'links' => [public_path('storage') => storage_path('app/public')],
];
