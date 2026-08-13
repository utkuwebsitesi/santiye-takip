<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->databaseIsAvailable(),
            'storage' => is_writable(storage_path('framework')) && is_writable(storage_path('logs')),
            'cache' => is_writable(base_path('bootstrap/cache')),
        ];

        $healthy = ! in_array(false, $checks, true);

        return response()->json([
            'status' => $healthy ? 'ok' : 'unavailable',
            'checks' => $checks,
            'checked_at' => now()->toIso8601String(),
        ], $healthy ? 200 : 503, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
        ]);
    }

    private function databaseIsAvailable(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
