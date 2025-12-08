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

        // Credit notes
        'credit_note.create',
        'credit_note.view',
        'credit_note.apply',
        'credit_note.void',

        // AP / GL
        'account.create',
        'account.view',
        'account.update',
        'account.delete',
        'journal.create',
        'journal.view',
        'bill.create',
        'bill.view',
        'bill.update',
        'bill.delete',
        'bill.pay',
        'bill.void',
        'vendor.create',
        'vendor.view',
        'vendor.update',
        'vendor.delete',
        'vendor_credit.create',
        'vendor_credit.view',
        'vendor_credit.apply',
        'vendor_credit.void',

        // Tax Management
        'tax.manage',
        'tax.view',
        'tax.settings.update',
        'tax.rate.create',
        'tax.rate.update',
        'tax.rate.delete',
        'tax.group.create',
        'tax.group.update',
        'tax.group.delete',
        'tax.registration.create',
        'tax.registration.update',
        'tax.registration.delete',
        'tax.exemption.create',
        'tax.exemption.update',
        'tax.exemption.delete',
        'tax.calculate',
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

        // Credit notes
        'credit_note.create',
        'credit_note.view',
        'credit_note.apply',
        'credit_note.void',

        // AP / GL
        'account.create',
        'account.view',
        'account.update',
        'journal.create',
        'journal.view',
        'bill.create',
        'bill.view',
        'bill.update',
        'bill.pay',
        'vendor.create',
        'vendor.view',
        'vendor.update',
        'vendor_credit.create',
        'vendor_credit.view',
        'vendor_credit.apply',

        // Tax Management
        'tax.manage',
        'tax.view',
        'tax.settings.update',
        'tax.rate.create',
        'tax.rate.update',
        'tax.rate.delete',
        'tax.group.create',
        'tax.group.update',
        'tax.group.delete',
        'tax.registration.create',
        'tax.registration.update',
        'tax.registration.delete',
        'tax.exemption.create',
        'tax.exemption.update',
        'tax.exemption.delete',
        'tax.calculate',
    ],

    'member' => [
        // Customer (read only)
        'customer.view',

        // Invoice (read only)
        'invoice.view',

        // Payment (read only)
        'payment.view',

        // AP/GL read-only
        'account.view',
        'journal.view',
        'bill.view',
        'vendor.view',
        'vendor_credit.view',

        // Tax Management (read only)
        'tax.view',
    ],

];
