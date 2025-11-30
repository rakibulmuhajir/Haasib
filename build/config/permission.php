<?php

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

    'column_names' => [
        'model_morph_key' => 'model_id',
        'team_foreign_key' => 'company_id',
    ],

    'register_permission_check_method' => true,

    'display_permission_in_exception' => false,
    'display_role_in_exception' => false,
    'display_team_in_model' => true,

    'cache' => [
        'store' => 'default',
        'expiration_time' => \DateInterval::createFromDateString('24 hours'),
        'key' => 'spatie.permission.cache',
    ],

    'teams' => true,
    'team_model' => App\Models\Company::class,
    'team_foreign_key' => 'company_id',

    'enable_wildcard_permission' => false,

    'uuid' => true,
];
