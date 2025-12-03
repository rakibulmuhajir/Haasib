<?php

use App\Constants\Permissions;

/*
|--------------------------------------------------------------------------
| Role-Permission Matrix
|--------------------------------------------------------------------------
|
| Defines which permissions each role gets.
| This matrix is applied PER COMPANY when roles are synced.
|
| Roles: owner, accountant, viewer
|
| Run: php artisan app:sync-role-permissions
|
*/

return [

    'owner' => [
        // All permissions
        ...Permissions::all(),
    ],

    'admin' => [
        // Company
        'company.invite-user',
        'company.manage-users',
        'company.manage-roles',

        // Customer
        'customer.create',
        'customer.view',
        'customer.update',
        'customer.delete',

        // Invoice
        'invoice.create',
        'invoice.view',
        'invoice.update',
        'invoice.send',
        'invoice.void',

        // Payment
        'payment.create',
        'payment.view',
        'payment.void',
    ],

    'accountant' => [
        // Customer
        'customer.create',
        'customer.view',
        'customer.update',

        // Invoice
        'invoice.create',
        'invoice.view',
        'invoice.update',
        'invoice.send',
        'invoice.void',

        // Payment
        'payment.create',
        'payment.view',
        'payment.void',
    ],

    'member' => [
        // Customer (read only)
        'customer.view',

        // Invoice (read only)
        'invoice.view',

        // Payment (read only)
        'payment.view',
    ],

];
