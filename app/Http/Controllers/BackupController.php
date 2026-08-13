<?php

namespace App\Http\Controllers;

use App\Services\DatabaseBackupService;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class BackupController extends Controller
{
    public function store(DatabaseBackupService $backups): RedirectResponse
    {
        try {
            $path = $backups->create();

            return back()->with('success', 'Veritabanı yedeği oluşturuldu: '.basename($path));
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'backup' => 'Yedek oluşturulamadı. Hosting üzerinde mysqldump erişimini ve storage yazma iznini kontrol edin.',
            ]);
        }
    }

    public function download(string $filename): BinaryFileResponse
    {
        abort_unless(preg_match('/^santiye360-\d{8}-\d{6}\.sql\.gz$/', $filename) === 1, 404);
        $path = config('backup.directory').DIRECTORY_SEPARATOR.$filename;
        abort_unless(is_file($path), 404);

        return response()->download($path, $filename, [
            'Content-Type' => 'application/gzip',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
