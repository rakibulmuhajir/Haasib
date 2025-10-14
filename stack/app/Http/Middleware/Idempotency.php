<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class Idempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        // Only apply to write operations
        if (! $this->isWriteOperation($request)) {
            return $next($request);
        }

        $idempotencyKey = $this->getIdempotencyKey($request);
        
        if (! $idempotencyKey) {
            return $next($request);
        }

        // Check if this request was already processed
        $cachedResponse = Cache::get("idempotency:{$idempotencyKey}");
        
        if ($cachedResponse) {
            return response()->json(
                $cachedResponse['data'],
                $cachedResponse['status'],
                $cachedResponse['headers'] ?? []
            );
        }

        $response = $next($request);

        // Cache successful responses for idempotency
        if ($this->shouldCacheResponse($response)) {
            $this->cacheResponse($idempotencyKey, $response, $request);
        }

        return $response;
    }

    private function isWriteOperation(Request $request): bool
    {
        return in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE']);
    }

    private function getIdempotencyKey(Request $request): ?string
    {
        // Try header first (preferred method)
        $key = $request->header('Idempotency-Key');
        
        if ($key) {
            // Validate key format
            if (strlen($key) > 255 || ! preg_match('/^[a-zA-Z0-9\-_\.]+$/', $key)) {
                return null;
            }
            return $key;
        }

        // Fallback to request parameter (for CLI/form submissions)
        return $request->get('_idempotency_key');
    }

    private function shouldCacheResponse(Response $response): bool
    {
        // Only cache successful responses
        if ($response->getStatusCode() >= 400) {
            return false;
        }

        // Don't cache responses that are too large
        $content = $response->getContent();
        if ($content && strlen($content) > 1024 * 1024) { // 1MB limit
            return false;
        }

        return true;
    }

    private function cacheResponse(string $idempotencyKey, Response $response, Request $request): void
    {
        try {
            $cacheData = [
                'data' => json_decode($response->getContent(), true) ?? [],
                'status' => $response->getStatusCode(),
                'headers' => $this->getResponseHeaders($response),
                'cached_at' => now()->toISOString(),
                'original_request' => [
                    'method' => $request->method(),
                    'url' => $request->url(),
                    'user_id' => auth()->id(),
                ],
            ];

            // Cache for 24 hours
            Cache::put("idempotency:{$idempotencyKey}", $cacheData, now()->addHours(24));

        } catch (\Exception $e) {
            // Fail silently - cache errors shouldn't break the application
            logger()->error('Failed to cache idempotent response', [
                'error' => $e->getMessage(),
                'key' => $idempotencyKey,
            ]);
        }
    }

    private function getResponseHeaders(Response $response): array
    {
        // Extract relevant headers that should be preserved
        $relevantHeaders = [
            'content-type',
            'cache-control',
            'etag',
            'last-modified',
        ];

        $headers = [];
        foreach ($relevantHeaders as $header) {
            if ($response->headers->has($header)) {
                $headers[$header] = $response->headers->get($header);
            }
        }

        return $headers;
    }
}