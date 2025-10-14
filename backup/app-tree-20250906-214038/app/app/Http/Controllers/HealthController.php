<?php
// app/Http/Controllers/HealthController.php
namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthController
{
    public function __invoke(): JsonResponse
    {
        $db = false;
        $cache = false;

        try {
            DB::select('select 1'); // connectivity + permissions
            $db = true;
        } catch (\Throwable $e) {
            $db = false;
        }

        try {
            Cache::put('healthcheck', 'ok', 10);
            $cache = Cache::get('healthcheck') === 'ok';
        } catch (\Throwable $e) {
            $cache = false;
        }

        return response()->json([
            'ok' => $db && $cache,
            'db' => $db,
            'cache' => $cache,
            'time' => now()->toISOString(),
            'commit' => trim(@shell_exec('git rev-parse --short HEAD')) ?: null,
        ]);
    }
}
