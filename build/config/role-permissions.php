<?php

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
        // Full access to everything

        // Accounts
        'accounts_account_view',
        'accounts_account_create',
        'accounts_account_update',
        'accounts_account_delete',

        'accounts_journal_view',
        'accounts_journal_create',
        'accounts_journal_update',
        'accounts_journal_delete',
        'accounts_journal_post',
        'accounts_journal_reverse',

        'accounts_invoice_view',
        'accounts_invoice_create',
        'accounts_invoice_update',
        'accounts_invoice_delete',
        'accounts_invoice_approve',
        'accounts_invoice_void',
        'accounts_invoice_send',

        'accounts_report_view',
        'accounts_report_export',

        // Company management
        'company.create',
        'company.view',
        'company.update',
        'company.delete',
        'company.invite-user',
        'company.manage-users',
        'company.delete-user',
        'company.manage-roles',
        'company_settings_view',
        'company_settings_update',
        'company_members_view',
        'company_members_invite',
        'company_members_update_role',
        'company_members_remove',
    ],

    'accountant' => [
        // Can do accounting work, cannot manage company

        'accounts_account_view',
        'accounts_account_create',
        'accounts_account_update',
        // No delete

        'accounts_journal_view',
        'accounts_journal_create',
        'accounts_journal_update',
        'accounts_journal_post',
        // No delete, no reverse

        'accounts_invoice_view',
        'accounts_invoice_create',
        'accounts_invoice_update',
        'accounts_invoice_approve',
        'accounts_invoice_send',
        // No delete, no void

        'accounts_report_view',
        'accounts_report_export',

        // Limited company access
        'company_settings_view',
        'company_members_view',
    ],

    'viewer' => [
        // Read-only access

        'accounts_account_view',
        'accounts_journal_view',
        'accounts_invoice_view',
        'accounts_report_view',

        'company_settings_view',
        'company_members_view',
    ],

];
