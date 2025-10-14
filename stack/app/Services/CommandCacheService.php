<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class CommandCacheService
{
    private array $tags = ['command-palette'];

    private int $defaultTtl;

    private string $prefix;

    public function __construct()
    {
        $this->defaultTtl = config('command-palette.cache.ttl', 3600);
        $this->prefix = config('command-palette.cache.prefix', 'command_palette:');
    }

    /**
     * Cache command suggestions for a user
     */
    public function rememberSuggestions(int $userId, string $input, callable $callback): array
    {
        $key = $this->key('suggestions', $userId, md5($input));
        $ttl = config('command-palette.suggestions.cache_ttl', 1800);

        return Cache::remember($key, $ttl, $callback, $this->tags);
    }

    /**
     * Cache command list for a company
     */
    public function rememberCommands(int $companyId, callable $callback): array
    {
        $key = $this->key('commands', $companyId);

        return Cache::remember($key, $this->defaultTtl, $callback, $this->tags);
    }

    /**
     * Cache command metadata
     */
    public function rememberCommandMetadata(int $commandId, callable $callback): array
    {
        $key = $this->key('metadata', $commandId);

        return Cache::remember($key, $this->defaultTtl * 2, $callback, $this->tags);
    }

    /**
     * Cache execution history
     */
    public function rememberExecutionHistory(int $userId, int $companyId, callable $callback): array
    {
        $key = $this->key('history', $userId, $companyId);
        $ttl = config('command-palette.analytics.retention_days', 90) * 86400; // Convert to seconds

        return Cache::remember($key, min($ttl, $this->defaultTtl), $callback, $this->tags);
    }

    /**
     * Cache popular commands
     */
    public function rememberPopularCommands(int $companyId, int $days, callable $callback): array
    {
        $key = $this->key('popular', $companyId, $days);

        return Cache::remember($key, 1800, $callback, $this->tags); // 30 minutes
    }

    /**
     * Cache user command patterns
     */
    public function rememberUserPatterns(int $userId, callable $callback): array
    {
        $key = $this->key('patterns', $userId);
        $ttl = 86400; // 24 hours

        return Cache::remember($key, $ttl, $callback, $this->tags);
    }

    /**
     * Cache NLP results
     */
    public function rememberNlpResult(string $input, callable $callback): array
    {
        $key = $this->key('nlp', md5($input));
        $ttl = config('command-palette.cache.ttl', 3600);

        return Cache::remember($key, $ttl, $callback, $this->tags);
    }

    /**
     * Store temporary execution data
     */
    public function storeExecutionData(string $executionId, array $data): void
    {
        $key = $this->key('execution', $executionId);
        Cache::put($key, $data, 300, $this->tags); // 5 minutes
    }

    /**
     * Get temporary execution data
     */
    public function getExecutionData(string $executionId): ?array
    {
        $key = $this->key('execution', $executionId);

        return Cache::get($key);
    }

    /**
     * Clear execution data
     */
    public function clearExecutionData(string $executionId): void
    {
        $key = $this->key('execution', $executionId);
        Cache::forget($key, $this->tags);
    }

    /**
     * Increment command usage counter
     */
    public function incrementCommandUsage(int $commandId): void
    {
        $key = $this->key('usage', $commandId);
        Cache::increment($key, 1, $this->defaultTtl, $this->tags);
    }

    /**
     * Get command usage count
     */
    public function getCommandUsage(int $commandId): int
    {
        $key = $this->key('usage', $commandId);

        return (int) Cache::get($key, 0);
    }

    /**
     * Cache rate limit data
     */
    public function rememberRateLimit(string $key, callable $callback): array
    {
        $cacheKey = $this->key('rate_limit', $key);
        $ttl = config('command-palette.rate_limiting.decay_minutes', 1) * 60;

        return Cache::remember($cacheKey, $ttl, $callback, $this->tags);
    }

    /**
     * Store analytics data in batches
     */
    public function storeAnalyticsBatch(string $type, array $data): void
    {
        $key = $this->key('analytics_batch', $type, now()->format('Y-m-d-H-i'));

        Cache::add($key, [], 3600, $this->tags);

        $existing = Cache::get($key, []);
        $existing[] = $data;

        // Limit batch size
        if (count($existing) >= config('command-palette.analytics.batch_size', 100)) {
            $this->processAnalyticsBatch($type, $existing);
            Cache::put($key, [], 3600, $this->tags);
        } else {
            Cache::put($key, $existing, 3600, $this->tags);
        }
    }

    /**
     * Get analytics batch data
     */
    public function getAnalyticsBatch(string $type): array
    {
        $key = $this->key('analytics_batch', $type, now()->format('Y-m-d-H-i'));

        return Cache::get($key, []);
    }

    /**
     * Process analytics batch (move to persistent storage)
     */
    protected function processAnalyticsBatch(string $type, array $data): void
    {
        // This would typically queue a job to process the batch
        Log::info('Processing analytics batch', [
            'type' => $type,
            'count' => count($data),
            'timestamp' => now(),
        ]);
    }

    /**
     * Clear all command palette cache
     */
    public function clear(): void
    {
        Cache::tags($this->tags)->flush();
    }

    /**
     * Clear user-specific cache
     */
    public function clearUserCache(int $userId): void
    {
        $patterns = [
            $this->key('suggestions', $userId, '*'),
            $this->key('history', $userId, '*'),
            $this->key('patterns', $userId),
        ];

        foreach ($patterns as $pattern) {
            if (function_exists('Redis')) {
                $keys = Redis::keys($pattern);
                if (! empty($keys)) {
                    Redis::del($keys);
                }
            }
        }
    }

    /**
     * Clear company-specific cache
     */
    public function clearCompanyCache(int $companyId): void
    {
        $patterns = [
            $this->key('commands', $companyId),
            $this->key('popular', $companyId, '*'),
        ];

        foreach ($patterns as $pattern) {
            if (function_exists('Redis')) {
                $keys = Redis::keys($pattern);
                if (! empty($keys)) {
                    Redis::del($keys);
                }
            }
        }
    }

    /**
     * Warm up cache for a user
     */
    public function warmUpUserCache(int $userId, int $companyId): void
    {
        // Preload common queries
        $this->rememberCommands($companyId, function () use ($companyId) {
            return \App\Models\Command::where('company_id', $companyId)
                ->where('is_active', true)
                ->get()
                ->toArray();
        });

        $this->rememberUserPatterns($userId, function () use ($userId) {
            return \App\Models\CommandHistory::where('user_id', $userId)
                ->where('execution_status', 'success')
                ->where('executed_at', '>=', now()->subDays(30))
                ->get()
                ->groupBy('command_id')
                ->map(fn ($group) => $group->count())
                ->toArray();
        });
    }

    /**
     * Generate cache key
     */
    private function key(...$parts): string
    {
        return $this->prefix.implode(':', $parts);
    }

    /**
     * Get cache statistics
     */
    public function getStats(): array
    {
        $stats = [
            'driver' => config('command-palette.cache.driver', 'redis'),
            'prefix' => $this->prefix,
            'default_ttl' => $this->defaultTtl,
            'tags' => $this->tags,
        ];

        if (function_exists('Redis')) {
            try {
                $redis = Redis::connection();
                $keys = $redis->keys($this->prefix.'*');
                $stats['total_keys'] = count($keys);
                $stats['memory_usage'] = $redis->info('memory')['used_memory_human'] ?? 'N/A';
            } catch (\Exception $e) {
                $stats['redis_error'] = $e->getMessage();
            }
        }

        return $stats;
    }
}
