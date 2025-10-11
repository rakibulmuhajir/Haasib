<?php

use Illuminate\Support\Str;

return [
    /*
    |--------------------------------------------------------------------------
    | Accounting Redis Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration handles Redis connections for the accounting module,
    | providing separate connections for caching, queues, and sessions with
    | proper multi-tenant isolation.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Default Accounting Redis Connection
    |--------------------------------------------------------------------------
    */
    'default' => env('ACCOUNTING_REDIS_CONNECTION', 'cache'),

    /*
    |--------------------------------------------------------------------------
    | Accounting Redis Connections
    |--------------------------------------------------------------------------
    |
    | These are the Redis connections used by the accounting module.
    | Each connection is optimized for specific use cases.
    |
    */
    'connections' => [
        // Main cache for accounting data
        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_ACCOUNTING_CACHE_DB', '2'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'haasib'))).'.acct.cache.',
            'options' => [
                'serializer' => defined('Redis::SERIALIZER_JSON') ? Redis::SERIALIZER_JSON : 1,
                'compression' => defined('Redis::COMPRESSION_LZ4') ? Redis::COMPRESSION_LZ4 : (defined('Redis::COMPRESSION_LZF') ? Redis::COMPRESSION_LZF : 0),
            ],
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

        // Queue for accounting jobs (journal entries, reports, etc.)
        'queue' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_ACCOUNTING_QUEUE_DB', '3'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'haasib'))).'.acct.queue.',
            'options' => [
                'block_timeout' => 10,
                'read_timeout' => 30,
                'serializer' => defined('Redis::SERIALIZER_JSON') ? Redis::SERIALIZER_JSON : 1,
            ],
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

        // Session storage for accounting context
        'session' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_ACCOUNTING_SESSION_DB', '4'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'haasib'))).'.acct.session.',
            'options' => [
                'serializer' => defined('Redis::SERIALIZER_PHP') ? Redis::SERIALIZER_PHP : 0,
                'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'haasib'))).'.acct.session.',
            ],
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
        ],

        // Cache for financial reports (with longer TTL)
        'reports' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_ACCOUNTING_REPORTS_DB', '5'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'haasib'))).'.acct.reports.',
            'options' => [
                'serializer' => defined('Redis::SERIALIZER_JSON') ? Redis::SERIALIZER_JSON : 1,
                'compression' => defined('Redis::COMPRESSION_LZ4') ? Redis::COMPRESSION_LZ4 : (defined('Redis::COMPRESSION_LZF') ? Redis::COMPRESSION_LZF : 0),
            ],
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
        ],

        // Temporary storage for batch operations
        'batch' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_ACCOUNTING_BATCH_DB', '6'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'haasib'))).'.acct.batch.',
            'options' => [
                'serializer' => defined('Redis::SERIALIZER_JSON') ? Redis::SERIALIZER_JSON : 1,
                'expire' => 86400, // 24 hours default TTL
            ],
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
        ],

        // Lock management for concurrent operations
        'locks' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_ACCOUNTING_LOCKS_DB', '7'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'haasib'))).'.acct.locks.',
            'options' => [
                'serializer' => defined('Redis::SERIALIZER_NONE') ? Redis::SERIALIZER_NONE : 0,
            ],
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Tags for Accounting
    |--------------------------------------------------------------------------
    |
    | Define cache tags for different types of accounting data to allow
    | for selective cache invalidation.
    |
    */
    'cache_tags' => [
        'chart_of_accounts' => 'coa',
        'journal_entries' => 'je',
        'trial_balance' => 'tb',
        'financial_reports' => 'fr',
        'fiscal_years' => 'fy',
        'user_permissions' => 'perm',
        'company_settings' => 'cs',
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Names for Accounting
    |--------------------------------------------------------------------------
    |
    | Define specific queue names for different accounting operations.
    |
    */
    'queues' => [
        'journal_posting' => 'accounting.journal.post',
        'report_generation' => 'accounting.reports.generate',
        'period_closing' => 'accounting.periods.close',
        'batch_imports' => 'accounting.imports.batch',
        'export_jobs' => 'accounting.exports',
        'notifications' => 'accounting.notifications',
        'reconciliation' => 'accounting.reconcile',
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Keys for Accounting
    |--------------------------------------------------------------------------
    |
    | Define session keys used by the accounting module.
    |
    */
    'session_keys' => [
        'current_company_id' => 'acct_current_company',
        'active_fiscal_year' => 'acct_fiscal_year',
        'active_period' => 'acct_period',
        'user_permissions' => 'acct_permissions',
        'recent_accounts' => 'acct_recent_accounts',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache TTL Settings (in seconds)
    |--------------------------------------------------------------------------
    |
    | Define default TTL values for different types of cached data.
    |
    */
    'ttl' => [
        'chart_of_accounts' => 86400,        // 24 hours
        'account_balances' => 3600,          // 1 hour
        'journal_entries' => 1800,           // 30 minutes
        'trial_balance' => 1800,             // 30 minutes
        'financial_reports' => 86400,        // 24 hours
        'user_permissions' => 3600,          // 1 hour
        'company_settings' => 86400,         // 24 hours
        'exchange_rates' => 86400,           // 24 hours
        'tax_rates' => 86400,                // 24 hours
        'reports_cache' => 43200,            // 12 hours
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-tenant Isolation Settings
    |--------------------------------------------------------------------------
    |
    | Configure how Redis isolates data between tenants.
    |
    */
    'multi_tenant' => [
        'isolate_by_company' => true,
        'key_separator' => ':',
        'company_prefix' => 'company_{company_id}',
        'user_prefix' => 'user_{user_id}',
    ],
];
