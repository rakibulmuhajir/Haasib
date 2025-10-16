<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Accounting Queue Workers Configuration
    |--------------------------------------------------------------------------
    |
    | This file defines the queue workers for accounting operations.
    | Each worker handles specific types of accounting jobs.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Default Queue Worker
    |--------------------------------------------------------------------------
    |
    | The default queue worker configuration for accounting jobs.
    |
    */
    'default' => [
        'connection' => env('ACCOUNTING_QUEUE_CONNECTION', 'accounting'),
        'queue' => ['accounting_default', 'accounting_high'],
        'sleep' => 3,
        'tries' => 3,
        'timeout' => 60,
        'backoff' => [30, 60, 120],
        'memory' => 256,
        'delay' => 0,
        'force' => false,
        'stopWhenEmpty' => false,
        'maxTime' => 0,
        'maxJobs' => 0,
    ],

    /*
    |--------------------------------------------------------------------------
    | High Priority Queue Worker
    |--------------------------------------------------------------------------
    |
    | Worker for high priority accounting jobs like posting critical entries.
    |
    */
    'high_priority' => [
        'connection' => env('ACCOUNTING_QUEUE_CONNECTION', 'accounting'),
        'queue' => ['accounting_high', 'accounting_critical'],
        'sleep' => 1,
        'tries' => 5,
        'timeout' => 120,
        'backoff' => [5, 10, 20, 30, 60],
        'memory' => 512,
        'delay' => 0,
        'force' => false,
        'stopWhenEmpty' => false,
        'maxTime' => 0,
        'maxJobs' => 0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Report Generation Queue Worker
    |--------------------------------------------------------------------------
    |
    | Worker for financial report generation jobs. These can be memory intensive.
    |
    */
    'reports' => [
        'connection' => env('ACCOUNTING_QUEUE_CONNECTION', 'accounting'),
        'queue' => ['accounting_reports'],
        'sleep' => 5,
        'tries' => 2,
        'timeout' => 300,
        'backoff' => [60, 300],
        'memory' => 1024,
        'delay' => 0,
        'force' => false,
        'stopWhenEmpty' => false,
        'maxTime' => 0,
        'maxJobs' => 0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Batch Processing Queue Worker
    |--------------------------------------------------------------------------
    |
    | Worker for batch imports and exports. These jobs should be processed
    | during off-peak hours.
    |
    */
    'batch' => [
        'connection' => env('ACCOUNTING_QUEUE_CONNECTION', 'accounting'),
        'queue' => ['accounting_batch', 'accounting_imports', 'accounting_exports'],
        'sleep' => 10,
        'tries' => 1,
        'timeout' => 1800, // 30 minutes
        'backoff' => [300, 900],
        'memory' => 2048,
        'delay' => 0,
        'force' => false,
        'stopWhenEmpty' => true,
        'maxTime' => 21600, // 6 hours
        'maxJobs' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Queue Worker
    |--------------------------------------------------------------------------
    |
    | Worker for sending accounting notifications (invoices, statements, etc.).
    |
    */
    'notifications' => [
        'connection' => env('ACCOUNTING_QUEUE_CONNECTION', 'accounting'),
        'queue' => ['accounting_notifications'],
        'sleep' => 2,
        'tries' => 3,
        'timeout' => 30,
        'backoff' => [10, 30, 60],
        'memory' => 128,
        'delay' => 0,
        'force' => false,
        'stopWhenEmpty' => false,
        'maxTime' => 0,
        'maxJobs' => 0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Reconciliation Queue Worker
    |--------------------------------------------------------------------------
    |
    | Worker for bank reconciliation jobs. These require database locks
    | and should be processed carefully.
    |
    */
    'reconciliation' => [
        'connection' => env('ACCOUNTING_QUEUE_CONNECTION', 'accounting'),
        'queue' => ['accounting_reconcile'],
        'sleep' => 3,
        'tries' => 5,
        'timeout' => 600, // 10 minutes
        'backoff' => [60, 300, 900],
        'memory' => 512,
        'delay' => 0,
        'force' => false,
        'stopWhenEmpty' => false,
        'maxTime' => 0,
        'maxJobs' => 0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Journal Entry Queue Worker
    |--------------------------------------------------------------------------
    |
    | Worker for manual journal entry processing, approvals, and posting.
    | These jobs require balance validation and audit trail integrity.
    |
    */
    'journal' => [
        'connection' => env('ACCOUNTING_QUEUE_CONNECTION', 'accounting'),
        'queue' => ['journal', 'journal_approval', 'journal_posting'],
        'sleep' => 2,
        'tries' => 3,
        'timeout' => 90,
        'backoff' => [5, 15, 30],
        'memory' => 256,
        'delay' => 0,
        'force' => false,
        'stopWhenEmpty' => false,
        'maxTime' => 0,
        'maxJobs' => 0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Ledger Processing Queue Worker
    |--------------------------------------------------------------------------
    |
    | Worker for automatic ledger posting from source documents like invoices
    | and payments. These jobs maintain double-entry integrity.
    |
    */
    'ledger' => [
        'connection' => env('ACCOUNTING_QUEUE_CONNECTION', 'accounting'),
        'queue' => ['ledger', 'ledger_auto_post', 'ledger_reconciliation'],
        'sleep' => 1,
        'tries' => 5,
        'timeout' => 120,
        'backoff' => [10, 30, 60],
        'memory' => 512,
        'delay' => 0,
        'force' => false,
        'stopWhenEmpty' => false,
        'maxTime' => 0,
        'maxJobs' => 0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Supervisor Configuration Examples
    |--------------------------------------------------------------------------
    |
    | These are example configurations for Laravel Supervisor.
    | Copy to your supervisord.conf file and adjust as needed.
    |
    */
    'supervisor_configs' => [
        'accounting_default' => [
            'program' => 'accounting_default_worker',
            'command' => 'php artisan queue:work accounting --queue=accounting_default,accounting_high --sleep=3 --tries=3 --memory=256',
            'autostart' => true,
            'autorestart' => true,
            'user' => 'www-data',
            'numprocs' => 2,
            'redirect_stderr' => true,
            'stdout_logfile' => storage_path('logs/supervisor-accounting-default.log'),
            'stopwaitsecs' => 3600,
        ],
        'accounting_reports' => [
            'program' => 'accounting_reports_worker',
            'command' => 'php artisan queue:work accounting --queue=accounting_reports --sleep=5 --tries=2 --memory=1024 --timeout=300',
            'autostart' => true,
            'autorestart' => true,
            'user' => 'www-data',
            'numprocs' => 1,
            'redirect_stderr' => true,
            'stdout_logfile' => storage_path('logs/supervisor-accounting-reports.log'),
            'stopwaitsecs' => 3600,
        ],
        'accounting_batch' => [
            'program' => 'accounting_batch_worker',
            'command' => 'php artisan queue:work accounting --queue=accounting_batch --sleep=10 --tries=1 --memory=2048 --timeout=1800 --stop-when-empty',
            'autostart' => false,  // Start manually or via cron during off-peak
            'autorestart' => false,
            'user' => 'www-data',
            'numprocs' => 1,
            'redirect_stderr' => true,
            'stdout_logfile' => storage_path('logs/supervisor-accounting-batch.log'),
            'stopwaitsecs' => 3600,
        ],
        'accounting_journal' => [
            'program' => 'accounting_journal_worker',
            'command' => 'php artisan queue:work accounting --queue=journal,journal_approval,journal_posting --sleep=2 --tries=3 --memory=256 --timeout=90',
            'autostart' => true,
            'autorestart' => true,
            'user' => 'www-data',
            'numprocs' => 2,
            'redirect_stderr' => true,
            'stdout_logfile' => storage_path('logs/supervisor-accounting-journal.log'),
            'stopwaitsecs' => 3600,
        ],
        'accounting_ledger' => [
            'program' => 'accounting_ledger_worker',
            'command' => 'php artisan queue:work accounting --queue=ledger,ledger_auto_post,ledger_reconciliation --sleep=1 --tries=5 --memory=512 --timeout=120',
            'autostart' => true,
            'autorestart' => true,
            'user' => 'www-data',
            'numprocs' => 3,
            'redirect_stderr' => true,
            'stdout_logfile' => storage_path('logs/supervisor-accounting-ledger.log'),
            'stopwaitsecs' => 3600,
        ],
    ],
];
