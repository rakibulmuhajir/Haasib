<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Accounting Module Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for the Accounting module including
    | default settings, feature flags, and system options.
    |
    */

    'name' => 'Accounting',
    'key' => 'accounting',
    'description' => 'Core accounting infrastructure with multi-tenancy support',
    'version' => '1.0.0',
    'category' => 'core',
    'author' => 'Haasib Team',
    'license' => 'MIT',

    /*
    |--------------------------------------------------------------------------
    | Dependencies
    |--------------------------------------------------------------------------
    |
    | Other modules that this module depends on. These modules must be
    | enabled before this module can be activated.
    |
    */
    'dependencies' => [],

    /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    |
    | List of permissions that this module provides. These will be
    | automatically registered when the module is enabled.
    |
    */
    'permissions' => [
        // User management
        'view_users' => [
            'name' => 'View Users',
            'description' => 'View user list and details',
            'roles' => ['owner', 'admin', 'accountant', 'viewer'],
        ],
        'manage_users' => [
            'name' => 'Manage Users',
            'description' => 'Create, update, and deactivate users',
            'roles' => ['owner', 'admin'],
        ],
        'invite_users' => [
            'name' => 'Invite Users',
            'description' => 'Invite users to join the company',
            'roles' => ['owner', 'admin'],
        ],
        'remove_users' => [
            'name' => 'Remove Users',
            'description' => 'Remove users from the company',
            'roles' => ['owner', 'admin'],
        ],

        // Company management
        'view_company' => [
            'name' => 'View Company',
            'description' => 'View company details and settings',
            'roles' => ['owner', 'admin', 'accountant', 'viewer', 'member'],
        ],
        'manage_company' => [
            'name' => 'Manage Company',
            'description' => 'Update company settings and information',
            'roles' => ['owner', 'admin'],
        ],
        'transfer_ownership' => [
            'name' => 'Transfer Ownership',
            'description' => 'Transfer company ownership to another user',
            'roles' => ['owner'],
        ],

        // Module management
        'view_modules' => [
            'name' => 'View Modules',
            'description' => 'View available and enabled modules',
            'roles' => ['owner', 'admin'],
        ],
        'manage_modules' => [
            'name' => 'Manage Modules',
            'description' => 'Enable and disable modules',
            'roles' => ['owner', 'admin'],
        ],

        // Audit and security
        'view_audit_log' => [
            'name' => 'View Audit Log',
            'description' => 'View audit trail and activity logs',
            'roles' => ['owner', 'admin'],
        ],
        'export_data' => [
            'name' => 'Export Data',
            'description' => 'Export company data and reports',
            'roles' => ['owner', 'admin', 'accountant'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Settings Schema
    |--------------------------------------------------------------------------
    |
    | Define the structure and validation rules for module settings.
    | These will be used to validate settings when the module is enabled.
    |
    */
    'settings_schema' => [
        'audit' => [
            'type' => 'object',
            'properties' => [
                'log_all_actions' => [
                    'type' => 'boolean',
                    'default' => true,
                    'description' => 'Log all user actions in audit trail',
                ],
                'retain_days' => [
                    'type' => 'integer',
                    'default' => 365,
                    'min' => 30,
                    'max' => 2555,
                    'description' => 'Number of days to retain audit logs',
                ],
                'log_ip_addresses' => [
                    'type' => 'boolean',
                    'default' => true,
                    'description' => 'Log IP addresses in audit entries',
                ],
                'log_user_agent' => [
                    'type' => 'boolean',
                    'default' => true,
                    'description' => 'Log user agent strings in audit entries',
                ],
            ],
        ],
        'security' => [
            'type' => 'object',
            'properties' => [
                'require_2fa' => [
                    'type' => 'boolean',
                    'default' => false,
                    'description' => 'Require two-factor authentication for all users',
                ],
                'session_timeout' => [
                    'type' => 'integer',
                    'default' => 480,
                    'min' => 15,
                    'max' => 1440,
                    'description' => 'Session timeout in minutes',
                ],
                'max_login_attempts' => [
                    'type' => 'integer',
                    'default' => 5,
                    'min' => 3,
                    'max' => 10,
                    'description' => 'Maximum login attempts before lockout',
                ],
                'lockout_duration' => [
                    'type' => 'integer',
                    'default' => 15,
                    'min' => 5,
                    'max' => 60,
                    'description' => 'Account lockout duration in minutes',
                ],
            ],
        ],
        'users' => [
            'type' => 'object',
            'properties' => [
                'allow_registration' => [
                    'type' => 'boolean',
                    'default' => false,
                    'description' => 'Allow public user registration',
                ],
                'require_email_verification' => [
                    'type' => 'boolean',
                    'default' => true,
                    'description' => 'Require email verification for new users',
                ],
                'default_role' => [
                    'type' => 'string',
                    'enum' => ['member', 'viewer'],
                    'default' => 'member',
                    'description' => 'Default role for new users',
                ],
                'max_users_per_company' => [
                    'type' => 'integer',
                    'default' => 100,
                    'min' => 1,
                    'max' => 10000,
                    'description' => 'Maximum users allowed per company',
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Settings
    |--------------------------------------------------------------------------
    |
    | Default values for module settings when the module is first enabled.
    |
    */
    'default_settings' => [
        'audit' => [
            'log_all_actions' => true,
            'retain_days' => 365,
            'log_ip_addresses' => true,
            'log_user_agent' => true,
        ],
        'security' => [
            'require_2fa' => false,
            'session_timeout' => 480,
            'max_login_attempts' => 5,
            'lockout_duration' => 15,
        ],
        'users' => [
            'allow_registration' => false,
            'require_email_verification' => true,
            'default_role' => 'member',
            'max_users_per_company' => 100,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    |
    | Feature flags that can be enabled/disabled within the module.
    |
    */
    'features' => [
        'multi_tenancy' => true,
        'audit_logging' => true,
        'role_management' => true,
        'module_system' => true,
        'api_authentication' => true,
        'company_switching' => true,
        'user_invitations' => true,
        'export_functionality' => true,
        'cli_commands' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Path
    |--------------------------------------------------------------------------
    |
    | Path to the module's migration files.
    |
    */
    'migration_path' => __DIR__.'/../Database/Migrations',

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | Route configuration for the module.
    |
    */
    'routes' => [
        'api' => [
            'prefix' => 'api/accounting',
            'middleware' => ['api', 'auth:sanctum'],
            'file' => __DIR__.'/../Http/routes/api.php',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Service Providers
    |--------------------------------------------------------------------------
    |
    | Service providers that should be registered when the module is enabled.
    |
    */
    'providers' => [
        // Add service providers here
    ],

    /*
    |--------------------------------------------------------------------------
    | Commands
    |--------------------------------------------------------------------------
    |
    | Artisan commands provided by this module.
    |
    */
    'commands' => [
        'accounting:module' => \Modules\Accounting\Console\Commands\ModuleManagement::class,
        'accounting:company' => \Modules\Accounting\Console\Commands\CompanyManagement::class,
        'accounting:user' => \Modules\Accounting\Console\Commands\UserManagement::class,
        'accounting:audit' => \Modules\Accounting\Console\Commands\AuditReport::class,
        'accounting:superadmin' => \Modules\Accounting\Console\Commands\SuperAdmin::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Resources
    |--------------------------------------------------------------------------
    |
    | Additional resources like views, assets, etc.
    |
    */
    'resources' => [
        'views' => __DIR__.'/../Resources/views',
        'lang' => __DIR__.'/../Resources/lang',
        'assets' => __DIR__.'/../Resources/assets',
    ],
];
