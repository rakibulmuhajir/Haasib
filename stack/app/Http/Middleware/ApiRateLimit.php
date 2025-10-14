<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

class ApiRateLimit
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Get rate limits based on user's plan or system role
        $limits = $this->getRateLimits($user, $request);

        // Apply rate limiting for command execution endpoints
        if ($this->isCommandEndpoint($request)) {
            $this->checkRateLimit($user, 'commands', $limits['commands_per_minute'], 60);
        }

        // Apply general API rate limiting
        $this->checkRateLimit($user, 'api_general', $limits['requests_per_minute'], 60);

        return $next($request);
    }

    private function getRateLimits($user, Request $request): array
    {
        // Default limits
        $limits = [
            'requests_per_minute' => 100,
            'commands_per_minute' => 20,
        ];

        // Adjust based on user's system role
        switch ($user->system_role) {
            case 'system_owner':
            case 'super_admin':
                $limits['requests_per_minute'] = 500;
                $limits['commands_per_minute'] = 100;
                break;
            case 'admin':
                $limits['requests_per_minute'] = 300;
                $limits['commands_per_minute'] = 50;
                break;
            case 'manager':
                $limits['requests_per_minute'] = 200;
                $limits['commands_per_minute'] = 30;
                break;
        }

        // Adjust based on user's company role if applicable
        $company = $request->attributes->get('company');
        if ($company) {
            $companyUser = $company->users()->where('user_id', $user->id)->first();
            $role = $companyUser?->role ?? 'member';

            switch ($role) {
                case 'owner':
                case 'admin':
                    $limits['requests_per_minute'] *= 1.5;
                    $limits['commands_per_minute'] *= 1.5;
                    break;
                case 'manager':
                    $limits['requests_per_minute'] *= 1.2;
                    $limits['commands_per_minute'] *= 1.2;
                    break;
            }
        }

        return array_map('intval', $limits);
    }

    private function isCommandEndpoint(Request $request): bool
    {
        return str_starts_with($request->path(), 'api/commands');
    }

    private function checkRateLimit($user, string $key, int $maxAttempts, int $decaySeconds): void
    {
        $cacheKey = "rate_limit:{$key}:{$user->id}";

        try {
            $current = Redis::get($cacheKey);

            if ($current === null) {
                Redis::setex($cacheKey, $decaySeconds, 1);

                return;
            }

            $current = (int) $current;

            if ($current >= $maxAttempts) {
                abort(429, [
                    'error' => 'Too many requests',
                    'message' => "Rate limit exceeded for {$key}. Maximum {$maxAttempts} requests per {$decaySeconds} seconds.",
                    'retry_after' => Redis::ttl($cacheKey),
                ]);
            }

            Redis::incr($cacheKey);
        } catch (\Exception $e) {
            // If Redis is unavailable, allow the request but log the error
            \Log::warning('Rate limiting failed due to Redis error', [
                'user_id' => $user->id,
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
