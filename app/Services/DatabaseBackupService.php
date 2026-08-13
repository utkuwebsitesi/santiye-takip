<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class DatabaseBackupService
{
    public function create(): string
    {
        throw_unless(config('backup.enabled'), new RuntimeException('Otomatik yedekleme devre dışı.'));
        throw_unless(config('database.default') === 'mysql', new RuntimeException('Üretim yedeği yalnızca MySQL için çalışır.'));

        $connection = config('database.connections.mysql');
        $directory = config('backup.directory');
        File::ensureDirectoryExists($directory, 0700, true);

        $stamp = now()->format('Ymd-His');
        $finalPath = $directory.DIRECTORY_SEPARATOR."santiye360-{$stamp}.sql.gz";
        $partialPath = $finalPath.'.part';
        $credentialsPath = $directory.DIRECTORY_SEPARATOR.'.mysql-backup-'.bin2hex(random_bytes(8)).'.cnf';
        $stream = null;

        try {
            $credentials = "[client]\n"
                .'host='.$this->option((string) $connection['host'])."\n"
                .'port='.(int) $connection['port']."\n"
                .'user='.$this->option((string) $connection['username'])."\n"
                .'password='.$this->option((string) $connection['password'])."\n";
            throw_unless(File::put($credentialsPath, $credentials, true) !== false, new RuntimeException('Geçici yedek kimliği yazılamadı.'));
            @chmod($credentialsPath, 0600);

            $stream = gzopen($partialPath, 'wb9');
            throw_unless($stream !== false, new RuntimeException('Yedek dosyası oluşturulamadı.'));

            $process = new Process([
                (string) config('backup.mysqldump_path'),
                '--defaults-extra-file='.$credentialsPath,
                '--default-character-set=utf8mb4',
                '--single-transaction',
                '--quick',
                '--skip-lock-tables',
                '--routines',
                '--triggers',
                '--hex-blob',
                '--no-tablespaces',
                (string) $connection['database'],
            ]);
            $process->setTimeout(300);
            $process->run(function (string $type, string $buffer) use ($stream): void {
                if ($type === Process::OUT) {
                    gzwrite($stream, $buffer);
                }
            });

            throw_unless($process->isSuccessful(), new RuntimeException('MySQL yedeği alınamadı: '.trim($process->getErrorOutput())));
            gzclose($stream);
            $stream = null;
            throw_unless(filesize($partialPath) > 100, new RuntimeException('Oluşturulan yedek beklenenden küçük.'));
            throw_unless(rename($partialPath, $finalPath), new RuntimeException('Yedek tamamlanamadı.'));
            File::put($finalPath.'.sha256', hash_file('sha256', $finalPath).'  '.basename($finalPath).PHP_EOL, true);
            $this->prune();

            return $finalPath;
        } catch (Throwable $exception) {
            if (is_resource($stream)) {
                gzclose($stream);
            }
            @unlink($partialPath);
            throw $exception;
        } finally {
            @unlink($credentialsPath);
        }
    }

    private function option(string $value): string
    {
        return '"'.str_replace(['\\', '"', "\r", "\n"], ['\\\\', '\\"', '', ''], $value).'"';
    }

    private function prune(): void
    {
        $cutoff = now()->subDays(max(1, (int) config('backup.retention_days')))->getTimestamp();
        foreach (File::glob(config('backup.directory').DIRECTORY_SEPARATOR.'santiye360-*.sql.gz') ?: [] as $path) {
            if (filemtime($path) < $cutoff) {
                File::delete([$path, $path.'.sha256']);
            }
        }
    }
}
