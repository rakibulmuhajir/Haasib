<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Reporting Module Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration settings for the reporting module including feature flags,
    | permissions mapping, cache settings, and performance parameters.
    |
    */

    'enabled' => env('REPORTING_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Cache TTL settings for different types of reporting data. The dashboard
    | cache uses a short TTL for real-time data, while report results are
    | cached longer.
    |
    */
    'cache' => [
        'dashboard_ttl' => env('REPORTING_DASHBOARD_CACHE_TTL', 5), // 5 seconds
        'kpi_ttl' => env('REPORTING_KPI_CACHE_TTL', 300), // 5 minutes
        'reports_ttl' => env('REPORTING_REPORTS_CACHE_TTL', 3600), // 1 hour
        'trial_balance_ttl' => env('REPORTING_TRIAL_BALANCE_CACHE_TTL', 60), // 1 minute
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    |
    | Settings that control reporting performance and SLA targets.
    |
    */
    'performance' => [
        'dashboard_freshness_seconds' => env('REPORTING_DASHBOARD_FRESHNESS', 5),
        'report_generation_timeout' => env('REPORTING_GENERATION_TIMEOUT', 30),
        'max_report_records' => env('REPORTING_MAX_RECORDS', 10000),
        'concurrent_jobs' => env('REPORTING_CONCURRENT_JOBS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Materialized Views
    |--------------------------------------------------------------------------
    |
    | Settings for materialized views that power reporting queries.
    |
    */
    'materialized_views' => [
        'auto_refresh' => env('REPORTING_MV_AUTO_REFRESH', true),
        'refresh_interval' => env('REPORTING_MV_REFRESH_INTERVAL', 300), // 5 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Export Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for report exports and file handling.
    |
    */
    'exports' => [
        'storage_path' => env('REPORTING_EXPORTS_PATH', 'reports/exports'),
        'retention_days' => env('REPORTING_EXPORTS_RETENTION', 30),
        'download_token_ttl' => env('REPORTING_DOWNLOAD_TOKEN_TTL', 600), // 10 minutes
        'max_file_size_mb' => env('REPORTING_MAX_FILE_SIZE_MB', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default KPIs
    |--------------------------------------------------------------------------
    |
    | Default KPI definitions that are available to all companies.
    |
    */
    'default_kpis' => [
        'revenue' => [
            'code' => 'revenue',
            'name' => 'Revenue',
            'description' => 'Total revenue for the period',
            'visual_type' => 'stat',
            'value_format' => 'currency',
            'default_granularity' => 'monthly',
            'allow_drilldown' => true,
            'is_global' => true,
        ],
        'expenses' => [
            'code' => 'expenses',
            'name' => 'Expenses',
            'description' => 'Total expenses for the period',
            'visual_type' => 'stat',
            'value_format' => 'currency',
            'default_granularity' => 'monthly',
            'allow_drilldown' => true,
            'is_global' => true,
        ],
        'profit' => [
            'code' => 'profit',
            'name' => 'Net Profit',
            'description' => 'Net profit for the period',
            'visual_type' => 'trend',
            'value_format' => 'currency',
            'default_granularity' => 'monthly',
            'allow_drilldown' => true,
            'is_global' => true,
        ],
        'cash_balance' => [
            'code' => 'cash_balance',
            'name' => 'Cash Balance',
            'description' => 'Current cash balance',
            'visual_type' => 'stat',
            'value_format' => 'currency',
            'default_granularity' => 'daily',
            'allow_drilldown' => true,
            'is_global' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Permissions Mapping
    |--------------------------------------------------------------------------
    |
    | Map module permissions to user roles.
    |
    */
    'permissions' => [
        'Owner' => [
            'reporting.dashboard.view',
            'reporting.reports.generate',
            'reporting.reports.view',
            'reporting.reports.schedule',
            'reporting.reports.export',
            'reporting.templates.manage',
            'reporting.schedules.manage',
        ],
        'Accountant' => [
            'reporting.dashboard.view',
            'reporting.reports.generate',
            'reporting.reports.view',
            'reporting.reports.export',
            'reporting.templates.manage',
            'reporting.schedules.manage',
        ],
        'Viewer' => [
            'reporting.dashboard.view',
            'reporting.reports.view',
            'reporting.reports.export',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Queue settings for report generation and background jobs.
    |
    */
    'queues' => [
        'reports' => env('REPORTING_QUEUE', 'reports'),
        'dashboard' => env('REPORTING_DASHBOARD_QUEUE', 'dashboard'),
        'exports' => env('REPORTING_EXPORTS_QUEUE', 'exports'),
        'schedules' => env('REPORTING_SCHEDULES_QUEUE', 'schedules'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | Security and audit configuration for reporting.
    |
    */
    'security' => [
        'audit_report_access' => env('REPORTING_AUDIT_ACCESS', true),
        'max_export_records' => env('REPORTING_MAX_EXPORT_RECORDS', 50000),
        'require_export_approval' => env('REPORTING_REQUIRE_EXPORT_APPROVAL', false),
    ],
];
