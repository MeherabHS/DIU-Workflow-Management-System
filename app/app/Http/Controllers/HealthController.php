<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'storage' => $this->checkStorage(),
        ];

        $healthy = ! in_array('failed', $checks, true);

        if (! $healthy) {
            Log::warning('Health check failed.', ['checks' => $checks]);
        }

        return response()->json([
            'status' => $healthy ? 'ok' : 'error',
            'app' => 'DIUS Workflow Management Portal',
            'environment' => app()->environment(),
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }

    private function checkDatabase(): string
    {
        try {
            DB::select('select 1');

            return 'ok';
        } catch (Throwable) {
            return 'failed';
        }
    }

    private function checkStorage(): string
    {
        $paths = [
            storage_path(),
            storage_path('framework'),
            storage_path('logs'),
            storage_path('app/private'),
        ];

        foreach ($paths as $path) {
            if (! is_dir($path) || ! is_readable($path) || ! is_writable($path)) {
                return 'failed';
            }
        }

        return 'ok';
    }
}
