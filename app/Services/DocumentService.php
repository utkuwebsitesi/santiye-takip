<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentService
{
    public const MAX_KILOBYTES = 5120;

    public const MIMES = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

    public function store(UploadedFile $file, string $folder): string
    {
        $extension = strtolower($file->guessExtension() ?: $file->extension());
        abort_unless(in_array($extension, self::MIMES, true), 422, 'Geçersiz belge türü.');

        $path = trim($folder, '/').'/'.Str::uuid().'.'.$extension;
        Storage::disk('documents')->putFileAs(dirname($path), $file, basename($path));

        return $path;
    }

    public function exists(string $path): bool
    {
        return $this->safe($path) && Storage::disk('documents')->exists($path);
    }

    public function download(string $path, string $downloadName)
    {
        abort_unless($this->exists($path), 404);

        return Storage::disk('documents')->download($path, $downloadName, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function delete(string $path): void
    {
        if ($this->safe($path)) {
            Storage::disk('documents')->delete($path);
        }
    }

    private function safe(string $path): bool
    {
        return $path !== '' && ! str_contains($path, '..') && ! str_starts_with($path, '/') && ! str_contains($path, '\\');
    }
}
