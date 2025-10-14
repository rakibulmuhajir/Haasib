<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Local Environment Configuration
    |--------------------------------------------------------------------------
    |
    | Overrides for local development environment.
    |
    */

    'debug' => [
        'enabled' => true,
        'log_executions' => true,
        'include_stack_traces' => true,
        'performance_profiling' => true,
    ],

    'execution' => [
        'max_time' => 300, // 5 minutes for debugging
    ],

    'analytics' => [
        'enabled' => false, // Disable analytics in local
    ],

    'rate_limiting' => [
        'enabled' => false, // Disable rate limiting in local
    ],

    'suggestions' => [
        'confidence_threshold' => 0.5, // Lower threshold for testing
    ],

    'cache' => [
        'ttl' => 300, // 5 minutes - shorter cache for development
    ],

    'nlp' => [
        'service_url' => null, // Use mock service locally
        'timeout' => 10, // Longer timeout for debugging
    ],

    'features' => [
        'voice_input' => true, // Enable experimental features locally
        'multilingual_support' => true,
    ],

    'monitoring' => [
        'enabled' => false, // Disable monitoring in local
    ],
];
