<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class CommandPerformanceService
{
    private array $timers = [];

    private array $metrics = [];

    /**
     * Start timing an operation
     */
    public function startTimer(string $name): void
    {
        $this->timers[$name] = microtime(true);
    }

    /**
     * Stop timing an operation and record the duration
     */
    public function endTimer(string $name): float
    {
        if (! isset($this->timers[$name])) {
            return 0;
        }

        $duration = (microtime(true) - $this->timers[$name]) * 1000; // Convert to milliseconds
        unset($this->timers[$name]);

        $this->recordMetric($name, $duration);

        return $duration;
    }

    /**
     * Record a performance metric
     */
    public function recordMetric(string $name, float $value, array $tags = []): void
    {
        $metric = [
            'name' => $name,
            'value' => $value,
            'timestamp' => now()->toISOString(),
            'tags' => array_merge([
                'environment' => app()->environment(),
                'service' => 'command-palette',
            ], $tags),
        ];

        $this->metrics[] = $metric;

        // Log slow operations
        $threshold = config('command-palette.monitoring.thresholds.slow_query_ms', 1000);
        if ($value > $threshold) {
            Log::warning('Slow operation detected', [
                'operation' => $name,
                'duration_ms' => $value,
                'threshold_ms' => $threshold,
                'tags' => $metric['tags'],
            ]);
        }

        // Store in Redis for real-time monitoring
        $this->storeMetricInRedis($metric);
    }

    /**
     * Record command execution
     */
    public function recordCommandExecution(int $commandId, int $userId, int $companyId, string $status, float $duration): void
    {
        $this->recordMetric('command_execution', $duration, [
            'command_id' => $commandId,
            'user_id' => $userId,
            'company_id' => $companyId,
            'status' => $status,
        ]);

        // Record success/error rates
        $this->recordMetric('command_success_rate', $status === 'success' ? 1 : 0, [
            'command_id' => $commandId,
            'company_id' => $companyId,
        ]);
    }

    /**
     * Record cache hit/miss
     */
    public function recordCacheHit(string $cacheType, bool $hit): void
    {
        $this->recordMetric('cache_hit_rate', $hit ? 1 : 0, [
            'cache_type' => $cacheType,
        ]);
    }

    /**
     * Record NLP request
     */
    public function recordNlpRequest(string $input, float $duration, bool $success): void
    {
        $this->recordMetric('nlp_request_duration', $duration, [
            'success' => $success,
            'input_length' => strlen($input),
        ]);
    }

    /**
     * Record database query performance
     */
    public function recordDatabaseQuery(string $query, float $duration, int $rows): void
    {
        $this->recordMetric('database_query_duration', $duration, [
            'query_type' => $this->getQueryType($query),
            'rows_returned' => $rows,
        ]);
    }

    /**
     * Get performance summary for a time period
     */
    public function getPerformanceSummary(string $period = '1h'): array
    {
        $now = now();
        $from = match ($period) {
            '1h' => $now->copy()->subHour(),
            '24h' => $now->copy()->subDay(),
            '7d' => $now->copy()->subWeek(),
            '30d' => $now->copy()->subMonth(),
            default => $now->copy()->subHour(),
        };

        return [
            'period' => $period,
            'from' => $from->toISOString(),
            'to' => $now->toISOString(),
            'metrics' => $this->getMetricsFromRedis($from, $now),
        ];
    }

    /**
     * Get top performing commands
     */
    public function getTopCommands(int $limit = 10, int $days = 7): array
    {
        $key = $this->key('command_performance', now()->format('Y-m-d'));

        if (! Redis::exists($key)) {
            return [];
        }

        $data = Redis::zrevrange($key, 0, $limit - 1, ['WITHSCORES' => true]);

        $commands = [];
        foreach ($data as $commandId => $score) {
            $commands[] = [
                'command_id' => $commandId,
                'performance_score' => $score,
                'details' => $this->getCommandPerformanceDetails((int) $commandId, $days),
            ];
        }

        return $commands;
    }

    /**
     * Get slow operations
     */
    public function getSlowOperations(int $limit = 10, ?float $threshold = null): array
    {
        $threshold ??= config('command-palette.monitoring.thresholds.slow_query_ms', 1000);
        $key = $this->key('slow_operations');

        if (! Redis::exists($key)) {
            return [];
        }

        $data = Redis::zrevrangeByScore($key, '+inf', $threshold, ['WITHSCORES' => true, 'LIMIT' => 0, $limit]);

        $operations = [];
        foreach ($data as $operation => $duration) {
            $operations[] = [
                'operation' => $operation,
                'duration_ms' => $duration,
            ];
        }

        return $operations;
    }

    /**
     * Get error rates
     */
    public function getErrorRates(string $period = '1h'): array
    {
        $now = now();
        $from = match ($period) {
            '1h' => $now->copy()->subHour(),
            '24h' => $now->copy()->subDay(),
            '7d' => $now->copy()->subWeek(),
            default => $now->copy()->subHour(),
        };

        $key = $this->key('error_rates');

        if (! Redis::exists($key)) {
            return [];
        }

        $rates = [];
        $cursor = '0';

        do {
            $result = Redis::hscan($key, $cursor, 'MATCH', '*', 'COUNT', 100);
            $cursor = $result[0];

            foreach ($result[1] as $errorType => $count) {
                $rates[$errorType] = (int) $count;
            }
        } while ($cursor !== '0');

        return array_filter($rates, fn ($count) => $count > 0);
    }

    /**
     * Optimize performance based on collected metrics
     */
    public function optimize(): array
    {
        $optimizations = [];

        // Analyze cache hit rates
        $cacheStats = $this->analyzeCachePerformance();
        if ($cacheStats['hit_rate'] < 0.8) {
            $optimizations[] = [
                'type' => 'cache',
                'recommendation' => 'Consider increasing cache TTL or implementing more aggressive caching',
                'current_hit_rate' => $cacheStats['hit_rate'],
            ];
        }

        // Analyze slow operations
        $slowOps = $this->getSlowOperations(5);
        foreach ($slowOps as $op) {
            $optimizations[] = [
                'type' => 'query_optimization',
                'recommendation' => "Optimize slow operation: {$op['operation']}",
                'duration_ms' => $op['duration_ms'],
            ];
        }

        // Analyze command patterns
        $commandStats = $this->analyzeCommandPatterns();
        foreach ($commandStats['underutilized'] as $command) {
            $optimizations[] = [
                'type' => 'command_optimization',
                'recommendation' => "Consider optimizing underutilized command: {$command['name']}",
                'usage_count' => $command['usage_count'],
            ];
        }

        return $optimizations;
    }

    /**
     * Store metric in Redis for real-time monitoring
     */
    protected function storeMetricInRedis(array $metric): void
    {
        $key = $this->key('metrics', now()->format('Y-m-d-H-i'));
        Redis::lpush($key, json_encode($metric));
        Redis::expire($key, 3600); // Keep for 1 hour

        // Store aggregated metrics
        $this->updateAggregatedMetrics($metric);
    }

    /**
     * Update aggregated metrics
     */
    protected function updateAggregatedMetrics(array $metric): void
    {
        $hourKey = $this->key('hourly', $metric['name'], now()->format('Y-m-d-H'));
        Redis::incrby($hourKey, (int) $metric['value']);
        Redis::expire($hourKey, 86400); // Keep for 24 hours

        // Update slow operations tracking
        if ($metric['value'] > config('command-palette.monitoring.thresholds.slow_query_ms', 1000)) {
            $slowKey = $this->key('slow_operations');
            Redis::zadd($slowKey, [$metric['name'] => $metric['value']]);
            Redis::expire($slowKey, 86400);
        }

        // Update command performance
        if (str_contains($metric['name'], 'command_execution')) {
            $perfKey = $this->key('command_performance', now()->format('Y-m-d'));
            $commandId = $metric['tags']['command_id'] ?? 'unknown';
            $score = $metric['tags']['status'] === 'success' ? $metric['value'] : $metric['value'] * 10; // Penalize failures
            Redis::zadd($perfKey, [$commandId => $score]);
            Redis::expire($perfKey, 86400 * 7); // Keep for 7 days
        }
    }

    /**
     * Get metrics from Redis
     */
    protected function getMetricsFromRedis(Carbon $from, Carbon $now): array
    {
        $metrics = [];
        $current = $from->copy();

        while ($current <= $now) {
            $key = $this->key('metrics', $current->format('Y-m-d-H-i'));
            $data = Redis::lrange($key, 0, -1);

            foreach ($data as $item) {
                $metrics[] = json_decode($item, true);
            }

            $current->addMinute();
        }

        return $metrics;
    }

    /**
     * Get query type from SQL
     */
    protected function getQueryType(string $query): string
    {
        $query = strtoupper(trim($query));

        if (str_starts_with($query, 'SELECT')) {
            return 'select';
        } elseif (str_starts_with($query, 'INSERT')) {
            return 'insert';
        } elseif (str_starts_with($query, 'UPDATE')) {
            return 'update';
        } elseif (str_starts_with($query, 'DELETE')) {
            return 'delete';
        } else {
            return 'other';
        }
    }

    /**
     * Analyze cache performance
     */
    protected function analyzeCachePerformance(): array
    {
        $key = $this->key('cache_stats');

        if (! Redis::exists($key)) {
            return ['hit_rate' => 0, 'hits' => 0, 'misses' => 0];
        }

        $stats = Redis::hmget($key, ['hits', 'misses']);
        $hits = (int) ($stats[0] ?? 0);
        $misses = (int) ($stats[1] ?? 0);
        $total = $hits + $misses;

        return [
            'hit_rate' => $total > 0 ? $hits / $total : 0,
            'hits' => $hits,
            'misses' => $misses,
        ];
    }

    /**
     * Analyze command patterns
     */
    protected function analyzeCommandPatterns(): array
    {
        // This would analyze which commands are used most/least
        // For now, return placeholder data
        return [
            'most_used' => [],
            'least_used' => [],
            'underutilized' => [],
        ];
    }

    /**
     * Get command performance details
     */
    protected function getCommandPerformanceDetails(int $commandId, int $days): array
    {
        // Implementation would fetch detailed performance metrics for a specific command
        return [
            'command_id' => $commandId,
            'avg_duration' => 0,
            'success_rate' => 1.0,
            'usage_count' => 0,
        ];
    }

    /**
     * Generate cache key
     */
    private function key(...$parts): string
    {
        return 'command_palette:perf:'.implode(':', $parts);
    }

    /**
     * Get all recorded metrics
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }

    /**
     * Clear metrics
     */
    public function clearMetrics(): void
    {
        $this->metrics = [];
    }
}
