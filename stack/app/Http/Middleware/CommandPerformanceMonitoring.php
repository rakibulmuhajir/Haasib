<?php

namespace App\Http\Middleware;

use App\Services\CommandPerformanceService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CommandPerformanceMonitoring
{
    public function __construct(
        private CommandPerformanceService $performanceService
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shouldMonitor($request)) {
            return $next($request);
        }

        // Start performance monitoring
        $timerName = $this->getTimerName($request);
        $this->performanceService->startTimer($timerName);

        // Track memory usage before
        $memoryBefore = memory_get_usage(true);

        $response = $next($request);

        // Calculate performance metrics
        $duration = $this->performanceService->endTimer($timerName);
        $memoryAfter = memory_get_usage(true);
        $memoryUsed = $memoryAfter - $memoryBefore;

        // Record metrics
        $this->recordPerformanceMetrics($request, $response, $duration, $memoryUsed);

        // Add performance headers to response
        $this->addPerformanceHeaders($response, $duration, $memoryUsed);

        return $response;
    }

    /**
     * Determine if the request should be monitored
     */
    protected function shouldMonitor(Request $request): bool
    {
        // Only monitor command palette routes
        if (! str_starts_with($request->path(), 'api/commands')) {
            return false;
        }

        // Skip if monitoring is disabled
        if (! config('command-palette.monitoring.enabled', true)) {
            return false;
        }

        // Skip health check endpoints
        if (str_contains($request->path(), 'health')) {
            return false;
        }

        return true;
    }

    /**
     * Get timer name for the request
     */
    protected function getTimerName(Request $request): string
    {
        $route = $request->route();
        if (! $route) {
            return 'unknown_route';
        }

        return 'api_'.str_replace('.', '_', $route->getName());
    }

    /**
     * Record performance metrics
     */
    protected function recordPerformanceMetrics(
        Request $request,
        Response $response,
        float $duration,
        int $memoryUsed
    ): void {
        $tags = [
            'method' => $request->method(),
            'status' => $response->getStatusCode(),
            'user_id' => auth()->id() ?? 'anonymous',
            'company_id' => session('current_company_id') ?? session('active_company_id') ?? 'none',
        ];

        // Record API response time
        $this->performanceService->recordMetric('api_response_time', $duration, $tags);

        // Record memory usage
        $this->performanceService->recordMetric('memory_usage', $memoryUsed / 1024 / 1024, $tags); // MB

        // Record response status
        $this->performanceService->recordMetric('api_response_status', $response->getStatusCode(), $tags);

        // Log slow responses
        $threshold = config('command-palette.monitoring.thresholds.slow_query_ms', 1000);
        if ($duration > $threshold) {
            Log::warning('Slow API response detected', [
                'path' => $request->path(),
                'method' => $request->method(),
                'duration_ms' => $duration,
                'memory_mb' => round($memoryUsed / 1024 / 1024, 2),
                'status' => $response->getStatusCode(),
                'user_id' => auth()->id(),
            ]);
        }

        // Record error responses
        if ($response->getStatusCode() >= 400) {
            $this->performanceService->recordMetric('api_error_rate', 1, $tags);
        }
    }

    /**
     * Add performance headers to response
     */
    protected function addPerformanceHeaders(Response $response, float $duration, int $memoryUsed): void
    {
        // Only add headers in debug mode or for development
        if (! config('command-palette.debug.enabled', false) && app()->environment() === 'production') {
            return;
        }

        $response->headers->set('X-Response-Time', round($duration, 2).'ms');
        $response->headers->set('X-Memory-Usage', round($memoryUsed / 1024 / 1024, 2).'MB');
        $response->headers->set('X-Performance-Monitoring', 'Command Palette');
    }
}
