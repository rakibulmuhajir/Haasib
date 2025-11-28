<?php

use Spatie\Permission\PermissionRegistrar;

return [

    'models' => [
        'permission' => App\Models\Permission::class,
        'role' => App\Models\Role::class,
    ],

    'table_names' => [
        'roles' => 'roles',
        'permissions' => 'permissions',
        'model_has_permissions' => 'model_has_permissions',
        'model_has_roles' => 'model_has_roles',
        'role_has_permissions' => 'role_has_permissions',
    ],

    'cache' => [
        'store' => 'default',
        'expiration_time' => \DateInterval::createFromDateString('24 hours'),
        'key' => 'spatie.permission.cache',
    ],

    'display_permission_in_exception' => false,

    'enabled' => true,

    'register_permission_check_method' => true,

'teams' => true,

'team_model' => App\Models\Company::class,

'team_foreign_key' => 'company_id',

'column_names' => [
    'model_morph_key' => 'model_id',
],

'display_team_in_model' => true,

'wildcard_permission' => [
    'enabled' => false,
    'delimiter' => '.',
],

'inheritance' => [
    'enabled' => false,
],

'enable_exception_handling' => false,

'cache_duration' => PermissionRegistrar::CACHE_DURATION,

'uuid' => true,
];
