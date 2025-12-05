<?php

/*
|--------------------------------------------------------------------------
| Global Permissions
|--------------------------------------------------------------------------
|
| All permissions are defined here ONCE. They are GLOBAL.
| Roles are company-scoped, but permissions are not.
|
| Naming convention: module_model_action
|
| When you add a new feature, add its permissions here and run:
| php artisan app:sync-permissions
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Accounts Module
    |--------------------------------------------------------------------------
    */
    'accounts' => [

        // Chart of Accounts
        'account' => [
            'accounts_account_view',
            'accounts_account_create',
            'accounts_account_update',
            'accounts_account_delete',
        ],

        // Journal Entries
        'journal' => [
            'accounts_journal_view',
            'accounts_journal_create',
            'accounts_journal_update',
            'accounts_journal_delete',
            'accounts_journal_post',      // post/finalize a journal
            'accounts_journal_reverse',   // reverse a posted journal
        ],

        // Invoices
        'invoice' => [
            'accounts_invoice_view',
            'accounts_invoice_create',
            'accounts_invoice_update',
            'accounts_invoice_delete',
            'accounts_invoice_approve',
            'accounts_invoice_void',
            'accounts_invoice_send',      // send to customer
        ],

        // Reports
        'report' => [
            'accounts_report_view',
            'accounts_report_export',
        ],

        // Credit Notes (simple names used across accounting module)
        'credit_note' => [
            'credit_note.create',
            'credit_note.view',
            'credit_note.apply',
            'credit_note.void',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Company Management
    |--------------------------------------------------------------------------
    */
    'company' => [
        'core' => [
            'company.create',
            'company.view',
            'company.update',
            'company.delete',
            'company.invite-user',
            'company.manage-users',
            'company.delete-user',
            'company.manage-roles',
        ],
        'settings' => [
            'company_settings_view',
            'company_settings_update',
        ],

        'members' => [
            'company_members_view',
            'company_members_invite',
            'company_members_update_role',
            'company_members_remove',
        ],
    ],

];
