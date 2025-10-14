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
    ],
];
