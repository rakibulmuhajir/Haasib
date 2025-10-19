<?php

return [
    'modules' => [
        'accounting' => [
            'name' => 'Accounting',
            'namespace' => 'Modules\\Accounting',
            'provider' => Modules\Accounting\Providers\AccountingServiceProvider::class,
            'schema' => 'acct',
            'routes' => [
                'web' => true,
                'api' => true,
            ],
            'cli' => [
                'commands' => true,
                'palette' => true,
            ],
            'permissions' => [],
        ],
        'reporting' => [
            'name' => 'Reporting',
            'namespace' => 'Modules\\Reporting',
            'provider' => Modules\Reporting\Providers\ReportingServiceProvider::class,
            'schema' => 'rpt',
            'routes' => [
                'web' => true,
                'api' => true,
            ],
            'cli' => [
                'commands' => true,
                'palette' => false,
            ],
            'permissions' => [
                'reporting.dashboard.view',
                'reporting.reports.generate',
                'reporting.reports.view',
                'reporting.reports.schedule',
                'reporting.reports.export',
                'reporting.templates.manage',
                'reporting.schedules.manage',
            ],
        ],
    ],
];
