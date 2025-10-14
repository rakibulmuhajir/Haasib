<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Command Palette Monitoring Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for monitoring the health and performance of the
    | Command Palette feature.
    |
    */

    'enabled' => env('COMMAND_PALETTE_MONITORING_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Metrics Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for collecting performance and usage metrics.
    |
    */
    'metrics' => [
        'prefix' => 'command_palette',
        'collect_interval' => env('COMMAND_PALETTE_METRICS_INTERVAL', 60), // seconds
        'retention_days' => env('COMMAND_PALETTE_METRICS_RETENTION', 30),

        'metrics_to_collect' => [
            'execution_count',
            'execution_time',
            'success_rate',
            'error_rate',
            'cache_hit_rate',
            'suggestion_accuracy',
            'user_activity',
            'command_popularity',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Thresholds
    |--------------------------------------------------------------------------
    |
    | Threshold values for triggering performance alerts.
    |
    */
    'thresholds' => [
        'slow_query_ms' => env('COMMAND_PALETTE_SLOW_QUERY_THRESHOLD', 1000),
        'error_rate_percent' => env('COMMAND_PALETTE_ERROR_THRESHOLD', 5),
        'cache_hit_rate_percent' => env('COMMAND_PALETTE_CACHE_HIT_THRESHOLD', 80),
        'suggestion_confidence' => env('COMMAND_PALETTE_SUGGESTION_THRESHOLD', 0.7),
        'memory_usage_mb' => env('COMMAND_PALETTE_MEMORY_THRESHOLD', 512),
        'cpu_usage_percent' => env('COMMAND_PALETTE_CPU_THRESHOLD', 80),
    ],

    /*
    |--------------------------------------------------------------------------
    | Alert Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for sending alerts when thresholds are exceeded.
    |
    */
    'alerts' => [
        'enabled' => env('COMMAND_PALETTE_ALERTS_ENABLED', true),
        'channels' => explode(',', env('COMMAND_PALETTE_ALERT_CHANNELS', 'log')),
        'cooldown_minutes' => env('COMMAND_PALETTE_ALERT_COOLDOWN', 15),
        'severity_levels' => ['info', 'warning', 'error', 'critical'],

        'recipients' => [
            'email' => explode(',', env('COMMAND_PALETTE_ALERT_EMAILS', '')),
            'slack_webhook' => env('COMMAND_PALETTE_SLACK_WEBHOOK'),
            'teams_webhook' => env('COMMAND_PALETTE_TEAMS_WEBHOOK'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Checks
    |--------------------------------------------------------------------------
    |
    | Configuration for health check endpoints.
    |
    */
    'health_checks' => [
        'enabled' => env('COMMAND_PALETTE_HEALTH_CHECKS_ENABLED', true),
        'endpoint' => '/api/commands/health',
        'cache_ttl' => env('COMMAND_PALETTE_HEALTH_CACHE_TTL', 300), // 5 minutes

        'checks' => [
            'database_connection',
            'cache_connection',
            'nlp_service_health',
            'command_registry_status',
            'rate_limiter_status',
            'disk_space',
            'memory_usage',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for performance and debug logging.
    |
    */
    'logging' => [
        'channel' => env('COMMAND_PALETTE_LOG_CHANNEL', 'stack'),
        'level' => env('COMMAND_PALETTE_LOG_LEVEL', 'info'),
        'include_stack_traces' => env('COMMAND_PALETTE_LOG_STACK_TRACES', false),
        'max_files' => env('COMMAND_PALETTE_LOG_MAX_FILES', 30),

        'loggers' => [
            'performance' => [
                'enabled' => true,
                'slow_query_threshold' => 500, // ms
            ],
            'security' => [
                'enabled' => true,
                'log_all_attempts' => false,
                'log_failures_only' => true,
            ],
            'usage' => [
                'enabled' => true,
                'sample_rate' => 0.1, // 10% sampling
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for the monitoring dashboard.
    |
    */
    'dashboard' => [
        'enabled' => env('COMMAND_PALETTE_DASHBOARD_ENABLED', true),
        'route' => '/admin/command-palette/monitoring',
        'middleware' => ['web', 'auth', 'permission:view_monitoring'],
        'refresh_interval' => env('COMMAND_PALETTE_DASHBOARD_REFRESH', 30), // seconds

        'widgets' => [
            'overview_stats',
            'execution_timeline',
            'error_breakdown',
            'performance_metrics',
            'usage_analytics',
            'system_health',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Reporting Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for generating monitoring reports.
    |
    */
    'reports' => [
        'enabled' => env('COMMAND_PALETTE_REPORTS_ENABLED', true),
        'schedule' => env('COMMAND_PALETTE_REPORTS_SCHEDULE', 'daily'),
        'recipients' => explode(',', env('COMMAND_PALETTE_REPORT_RECIPIENTS', '')),

        'reports' => [
            'daily_summary',
            'weekly_performance',
            'monthly_analytics',
            'security_audit',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Integration Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for external monitoring services.
    |
    */
    'integrations' => [
        'prometheus' => [
            'enabled' => env('COMMAND_PALETTE_PROMETHEUS_ENABLED', false),
            'metrics_path' => '/metrics/command-palette',
            'namespace' => 'command_palette',
        ],

        'datadog' => [
            'enabled' => env('COMMAND_PALETTE_DATADOG_ENABLED', false),
            'api_key' => env('DATADOG_API_KEY'),
            'app_key' => env('DATADOG_APP_KEY'),
            'host' => env('DATADOG_HOST', 'api.datadoghq.com'),
        ],

        'newrelic' => [
            'enabled' => env('COMMAND_PALETTE_NEWRELIC_ENABLED', false),
            'app_name' => env('NEW_RELIC_APP_NAME', 'Laravel Command Palette'),
            'license_key' => env('NEW_RELIC_LICENSE_KEY'),
        ],
    ],
];
