<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Production Environment Configuration
    |--------------------------------------------------------------------------
    |
    | Overrides for production environment.
    |
    */

    'debug' => [
        'enabled' => false,
        'log_executions' => false,
        'include_stack_traces' => false,
        'performance_profiling' => false,
    ],

    'execution' => [
        'max_time' => 30, // Strict timeout for production
        'max_parameter_size' => 5120, // Smaller limit for security
    ],

    'analytics' => [
        'enabled' => true,
        'retention_days' => 365, // Keep analytics for a year
        'batch_size' => 200, // Larger batch size for efficiency
    ],

    'rate_limiting' => [
        'enabled' => true,
        'attempts' => 100, // Higher limit for production
        'decay_minutes' => 1,
    ],

    'suggestions' => [
        'confidence_threshold' => 0.8, // Higher threshold for better UX
        'limit' => 8, // Fewer suggestions for cleaner UI
    ],

    'cache' => [
        'ttl' => 7200, // 2 hours - longer cache for performance
        'driver' => 'redis', // Use Redis for production
    ],

    'security' => [
        'audit_all_executions' => true,
        'validate_permissions' => true,
        'log_failures' => true,
        'max_retries' => 2, // Fewer retries in production
    ],

    'performance' => [
        'eager_load_commands' => true,
        'prefetch_suggestions' => true,
        'batch_size' => 100,
        'query_timeout' => 5, // Shorter timeout
    ],

    'monitoring' => [
        'enabled' => true,
        'slow_query_threshold' => 500, // Lower threshold for production
        'error_threshold' => 2, // Lower error threshold
        'alert_channels' => 'log,email,slack',
    ],

    'features' => [
        'voice_input' => false, // Disable experimental features
        'multilingual_support' => false,
    ],

    'nlp' => [
        'timeout' => 3, // Shorter timeout for production
        'model' => 'production-optimized',
    ],
];
