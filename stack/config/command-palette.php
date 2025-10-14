<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Command Palette Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the Command Palette feature that provides a
    | keyboard-first interface for executing commands and actions.
    |
    */

    'enabled' => env('COMMAND_PALETTE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for caching command suggestions and metadata.
    |
    */
    'cache' => [
        'driver' => env('COMMAND_PALETTE_CACHE_DRIVER', 'redis'),
        'ttl' => env('COMMAND_PALETTE_CACHE_TTL', 3600), // 1 hour
        'prefix' => 'command_palette:',
    ],

    /*
    |--------------------------------------------------------------------------
    | Execution Configuration
    |--------------------------------------------------------------------------
    |
    | Settings related to command execution limits and security.
    |
    */
    'execution' => [
        'max_time' => env('COMMAND_PALETTE_MAX_EXECUTION_TIME', 30), // seconds
        'max_parameter_size' => env('COMMAND_PALETTE_MAX_PARAMETER_SIZE', 10240), // bytes
        'allowed_mime_types' => explode(',', env('COMMAND_PALETTE_ALLOWED_MIME_TYPES', 'application/json,text/plain')),
        'sanitize_parameters' => env('COMMAND_PALETTE_SANITIZE_PARAMETERS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Analytics Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for collecting command execution analytics.
    |
    */
    'analytics' => [
        'enabled' => env('COMMAND_PALETTE_ENABLE_ANALYTICS', true),
        'retention_days' => env('COMMAND_PALETTE_ANALYTICS_RETENTION_DAYS', 90),
        'batch_size' => env('COMMAND_PALETTE_ANALYTICS_BATCH_SIZE', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Suggestions Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for command suggestion algorithms and NLP integration.
    |
    */
    'suggestions' => [
        'enabled' => env('COMMAND_PALETTE_ENABLE_SUGGESTIONS', true),
        'limit' => env('COMMAND_PALETTE_SUGGESTION_LIMIT', 10),
        'confidence_threshold' => env('COMMAND_PALETTE_SUGGESTION_CONFIDENCE_THRESHOLD', 0.7),
        'cache_ttl' => env('COMMAND_PALETTE_SUGGESTIONS_CACHE_TTL', 1800), // 30 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Natural Language Processing
    |--------------------------------------------------------------------------
    |
    | Configuration for external NLP services used for command suggestions.
    |
    */
    'nlp' => [
        'service_url' => env('COMMAND_PALETTE_NLP_SERVICE_URL'),
        'api_key' => env('COMMAND_PALETTE_NLP_API_KEY'),
        'timeout' => env('COMMAND_PALETTE_NLP_TIMEOUT', 5),
        'model' => env('COMMAND_PALETTE_NLP_MODEL', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configuration for rate limiting command execution attempts.
    |
    */
    'rate_limiting' => [
        'enabled' => env('COMMAND_PALETTE_RATE_LIMIT_ENABLED', true),
        'attempts' => env('COMMAND_PALETTE_RATE_LIMIT_ATTEMPTS', 60),
        'decay_minutes' => env('COMMAND_PALETTE_RATE_LIMIT_DECAY_MINUTES', 1),
        'prefix' => 'command_palette:',
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Security-related settings for command execution.
    |
    */
    'security' => [
        'audit_all_executions' => env('COMMAND_PALETTE_AUDIT_ALL_EXECUTIONS', true),
        'validate_permissions' => env('COMMAND_PALETTE_VALIDATE_PERMISSIONS', true),
        'log_failures' => env('COMMAND_PALETTE_LOG_FAILURES', true),
        'max_retries' => env('COMMAND_PALETTE_MAX_RETRIES', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug Configuration
    |--------------------------------------------------------------------------
    |
    | Debug settings that vary by environment.
    |
    */
    'debug' => [
        'enabled' => env('COMMAND_PALETTE_DEBUG_MODE', false),
        'log_executions' => env('COMMAND_PALETTE_DEBUG_LOG_EXECUTIONS', false),
        'include_stack_traces' => env('COMMAND_PALETTE_DEBUG_INCLUDE_STACK_TRACES', false),
        'performance_profiling' => env('COMMAND_PALETTE_DEBUG_PERFORMANCE_PROFILING', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Configuration
    |--------------------------------------------------------------------------
    |
    | Performance optimization settings.
    |
    */
    'performance' => [
        'eager_load_commands' => env('COMMAND_PALETTE_EAGER_LOAD_COMMANDS', true),
        'prefetch_suggestions' => env('COMMAND_PALETTE_PREFETCH_SUGGESTIONS', false),
        'batch_size' => env('COMMAND_PALETTE_BATCH_SIZE', 50),
        'query_timeout' => env('COMMAND_PALETTE_QUERY_TIMEOUT', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Environment-Specific Overrides
    |--------------------------------------------------------------------------
    |
    | Configuration that varies by environment.
    |
    */
    'environments' => [
        'local' => [
            'debug.enabled' => true,
            'debug.log_executions' => true,
            'debug.include_stack_traces' => true,
            'debug.performance_profiling' => true,
            'execution.max_time' => 300, // 5 minutes
            'analytics.enabled' => false,
            'rate_limiting.enabled' => false,
            'suggestions.confidence_threshold' => 0.5,
        ],

        'testing' => [
            'debug.enabled' => true,
            'analytics.enabled' => false,
            'rate_limiting.enabled' => false,
            'execution.max_time' => 10,
            'nlp.service_url' => null, // Disable external services
        ],

        'staging' => [
            'debug.enabled' => true,
            'debug.log_executions' => true,
            'debug.include_stack_traces' => false,
            'execution.max_time' => 60, // 1 minute
            'suggestions.confidence_threshold' => 0.6,
        ],

        'production' => [
            'debug.enabled' => false,
            'cache.ttl' => 7200, // 2 hours
            'suggestions.cache_ttl' => 3600, // 1 hour
            'analytics.retention_days' => 365,
            'performance.eager_load_commands' => true,
            'performance.prefetch_suggestions' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for monitoring command palette health and performance.
    |
    */
    'monitoring' => [
        'enabled' => env('COMMAND_PALETTE_MONITORING_ENABLED', true),
        'metrics_prefix' => 'command_palette',
        'slow_query_threshold' => env('COMMAND_PALETTE_SLOW_QUERY_THRESHOLD', 1000), // ms
        'error_threshold' => env('COMMAND_PALETTE_ERROR_THRESHOLD', 5), // percentage
        'alert_channels' => env('COMMAND_PALETTE_ALERT_CHANNELS', 'log'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | Feature flags for enabling/disabling specific command palette features.
    |
    */
    'features' => [
        'natural_language_processing' => env('COMMAND_PALETTE_FEATURE_NLP', true),
        'batch_execution' => env('COMMAND_PALETTE_FEATURE_BATCH', true),
        'command_templates' => env('COMMAND_PALETTE_FEATURE_TEMPLATES', true),
        'execution_history' => env('COMMAND_PALETTE_FEATURE_HISTORY', true),
        'analytics_dashboard' => env('COMMAND_PALETTE_FEATURE_ANALYTICS', true),
        'keyboard_shortcuts' => env('COMMAND_PALETTE_FEATURE_KEYBOARD', true),
        'voice_input' => env('COMMAND_PALETTE_FEATURE_VOICE', false),
        'multilingual_support' => env('COMMAND_PALETTE_FEATURE_I18N', false),
    ],
];
