<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ApiRateLimit
{
    /**
     * Rate limit configuration
     */
    private const RATE_LIMIT = 60; // requests per minute

    private const WINDOW_DURATION = 60; // seconds

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Only apply to API routes
        if (! $this->isApiRoute($request)) {
            return $next($request);
        }

        $userId = $request->user()?->id;
        $ipAddress = $request->ip();

        // Create rate limit keys
        $userKey = $this->getRateLimitKey('user', $userId ?? $ipAddress);
        $ipKey = $this->getRateLimitKey('ip', $ipAddress);

        // Check user-based rate limit (if authenticated)
        if ($userId) {
            $userLimit = $this->checkRateLimit($userKey, self::RATE_LIMIT);

            if (! $userLimit['allowed']) {
                return $this->rateLimitResponse($userLimit);
            }
        }

        // Check IP-based rate limit (for all requests)
        $ipLimit = $this->checkRateLimit($ipKey, self::RATE_LIMIT * 2); // Higher limit for IP

        if (! $ipLimit['allowed']) {
            return $this->rateLimitResponse($ipLimit);
        }

        // Add rate limit headers to response
        $response = $next($request);

        if ($response instanceof JsonResponse) {
            $response->headers->set('X-RateLimit-Limit', self::RATE_LIMIT);
            $response->headers->set('X-RateLimit-Remaining', $userLimit['remaining'] ?? self::RATE_LIMIT);
            $response->headers->set('X-RateLimit-Reset', $userLimit['reset_at'] ?? now()->addMinute()->timestamp);
            $response->headers->set('X-RateLimit-Window', self::WINDOW_DURATION);
        }

        return $response;
    }

    /**
     * Check if the current route is an API route.
     */
    private function isApiRoute(Request $request): bool
    {
        return str_starts_with($request->path(), 'api/') ||
               str_starts_with($request->path(), 'api');
    }

    /**
     * Generate a rate limit key.
     */
    private function getRateLimitKey(string $type, string $identifier): string
    {
        return "rate_limit:{$type}:{$identifier}:".now()->format('Y-m-d-H:i');
    }

    /**
     * Check the rate limit for a given key.
     */
    private function checkRateLimit(string $key, int $limit): array
    {
        $current = Cache::get($key, 0);
        $remaining = max(0, $limit - $current);
        $allowed = $current < $limit;

        if (! $allowed) {
            Log::warning('Rate limit exceeded', [
                'key' => $key,
                'current' => $current,
                'limit' => $limit,
            ]);
        }

        return [
            'allowed' => $allowed,
            'current' => $current,
            'remaining' => $remaining,
            'limit' => $limit,
            'reset_at' => now()->addMinute()->timestamp,
        ];
    }

    /**
     * Create a rate limit response.
     */
    private function rateLimitResponse(array $limitInfo): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'Rate limit exceeded',
            'message' => 'Too many requests. Please wait before trying again.',
            'rate_limit_info' => [
                'limit' => $limitInfo['limit'],
                'current' => $limitInfo['current'],
                'remaining' => $limitInfo['remaining'],
                'reset_at' => $limitInfo['reset_at'],
                'window_duration' => self::WINDOW_DURATION,
            ],
        ], 429);
    }

    /**
     * Increment the rate limit counter.
     */
    public static function incrementRateLimit(string $type, string $identifier): void
    {
        $key = "rate_limit:{$type}:{$identifier}:".now()->format('Y-m-d-H:i');
        Cache::increment($key, 1);

        // Set expiration for the key
        if (! Cache::has($key)) {
            Cache::put($key, 1, now()->addSeconds(self::WINDOW_DURATION));
        }
    }
}
