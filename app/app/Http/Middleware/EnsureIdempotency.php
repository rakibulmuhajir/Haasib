<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EnsureIdempotency
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Only apply to write operations (POST, PUT, PATCH, DELETE)
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return $next($request);
        }

        $idempotencyKey = $request->header('Idempotency-Key');

        if (! $idempotencyKey) {
            return response()->json([
                'success' => false,
                'error' => 'Idempotency key required',
                'message' => 'Please provide an Idempotency-Key header for write operations',
            ], 400);
        }

        // Validate idempotency key format
        if (! Str::isUuid($idempotencyKey) && ! preg_match('/^[a-zA-Z0-9\-_]{8,255}$/', $idempotencyKey)) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid idempotency key',
                'message' => 'Idempotency key must be a UUID or alphanumeric string (8-255 characters)',
            ], 400);
        }

        // Create a unique cache key combining user, key, and method/path
        $cacheKey = $this->getCacheKey($request, $idempotencyKey);

        // Check if this key has been used before
        if (Cache::has($cacheKey)) {
            $cachedResponse = Cache::get($cacheKey);

            if ($cachedResponse['status'] === 'processing') {
                return response()->json([
                    'success' => false,
                    'error' => 'Request already processing',
                    'message' => 'This request is currently being processed',
                ], 409);
            }

            // Return the cached response
            return response()->json(
                $cachedResponse['data'],
                $cachedResponse['status_code']
            );
        }

        // Mark this key as being processed
        Cache::put($cacheKey, [
            'status' => 'processing',
            'timestamp' => now()->toISOString(),
        ], now()->addHours(24));

        try {
            $response = $next($request);

            // Cache successful responses (2xx status codes)
            if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                $responseData = $response->getData(true);

                Cache::put($cacheKey, [
                    'status' => 'completed',
                    'status_code' => $response->getStatusCode(),
                    'data' => $responseData,
                    'timestamp' => now()->toISOString(),
                ], now()->addHours(24));

                Log::info('Idempotent request cached', [
                    'idempotency_key' => $idempotencyKey,
                    'method' => $request->method(),
                    'path' => $request->path(),
                    'user_id' => $request->user()?->id,
                    'status_code' => $response->getStatusCode(),
                ]);
            } else {
                // Remove the processing marker for failed requests
                Cache::forget($cacheKey);
            }

            return $response;

        } catch (\Exception $e) {
            // Remove the processing marker on exception
            Cache::forget($cacheKey);

            Log::error('Idempotent request failed', [
                'idempotency_key' => $idempotencyKey,
                'method' => $request->method(),
                'path' => $request->path(),
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate a unique cache key for the idempotency request.
     */
    private function getCacheKey(Request $request, string $idempotencyKey): string
    {
        $userId = $request->user()?->id ?? 'anonymous';
        $method = $request->method();
        $path = $request->path();

        return "idempotency:{$userId}:{$method}:{$path}:{$idempotencyKey}";
    }

    /**
     * Clean up old idempotency keys.
     */
    public static function cleanupOldKeys(): void
    {
        // This could be called from a scheduled job
        // For now, we rely on cache expiration
    }
}
