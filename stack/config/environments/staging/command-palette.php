<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Staging Environment Configuration
    |--------------------------------------------------------------------------
    |
    | Overrides for staging environment - mirrors production with debugging.
    |
    */

    'debug' => [
        'enabled' => true,
        'log_executions' => true,
        'include_stack_traces' => false,
        'performance_profiling' => true,
    ],

    'execution' => [
        'max_time' => 60, // 1 minute for staging
    ],

    'analytics' => [
        'enabled' => true,
        'retention_days' => 90, // Standard retention
        'batch_size' => 100,
    ],

    'rate_limiting' => [
        'enabled' => true,
        'attempts' => 80, // Slightly lower than production
        'decay_minutes' => 1,
    ],

    'suggestions' => [
        'confidence_threshold' => 0.6, // Medium threshold for testing
        'limit' => 10,
    ],

    'cache' => [
        'ttl' => 3600, // 1 hour
        'driver' => 'redis',
    ],

    'security' => [
        'audit_all_executions' => true,
        'validate_permissions' => true,
        'log_failures' => true,
        'max_retries' => 3,
    ],

    'performance' => [
        'eager_load_commands' => true,
        'prefetch_suggestions' => false, // Disable prefetch for testing
        'batch_size' => 75,
        'query_timeout' => 8,
    ],

    'monitoring' => [
        'enabled' => true,
        'slow_query_threshold' => 750,
        'error_threshold' => 3,
        'alert_channels' => 'log,email',
    ],

    'features' => [
        'voice_input' => false,
        'multilingual_support' => false,
    ],

    'nlp' => [
        'timeout' => 5,
        'model' => 'staging-optimized',
    ],
];
