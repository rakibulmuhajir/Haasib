<?php

/*
|--------------------------------------------------------------------------
| Role-Permission Matrix
|--------------------------------------------------------------------------
|
| Defines which permissions each role gets.
| This matrix is applied PER COMPANY when roles are synced.
|
| Roles: owner, admin, accountant, viewer
|
| Run: php artisan rbac:sync-role-permissions
|
*/

use App\Constants\Permissions;

return [
    'owner' => [
        // Full access to everything
        Permissions::COMPANIES_VIEW,
        Permissions::COMPANIES_UPDATE,
        Permissions::COMPANIES_MANAGE_USERS,
        Permissions::USERS_VIEW,
        Permissions::USERS_CREATE,
        Permissions::USERS_UPDATE,
        Permissions::USERS_MANAGE_ROLES,
        
        // Full access to all company modules
        ...Permissions::getAllByModule()['acct.customers'],
        ...Permissions::getAllByModule()['acct.invoices'],
        ...Permissions::getAllByModule()['acct.payments'],
        ...Permissions::getAllByModule()['acct.allocations'],
        ...Permissions::getAllByModule()['ledger.entries'],
        ...Permissions::getAllByModule()['ledger.accounts'],
        ...Permissions::getAllByModule()['ledger.period_close'],
        ...Permissions::getAllByModule()['ledger.reconciliation'],
        ...Permissions::getAllByModule()['reporting.dashboard'],
        ...Permissions::getAllByModule()['reporting.reports'],
        ...Permissions::getAllByModule()['commands'],
        
        // Audit access
        Permissions::AUDIT_ENTRIES_VIEW,
        Permissions::AUDIT_ENTRIES_EXPORT,
        Permissions::COMPLIANCE_REPORTS_VIEW,
        Permissions::COMPLIANCE_REPORTS_GENERATE,
        
        // Required system permissions
        Permissions::RLS_CONTEXT,
    ],
    
    'admin' => [
        // Company management (no billing/destructive)
        Permissions::COMPANIES_VIEW,
        Permissions::COMPANIES_UPDATE,
        Permissions::COMPANIES_MANAGE_USERS,
        
        // User management within company
        Permissions::USERS_VIEW,
        Permissions::USERS_CREATE,
        Permissions::USERS_UPDATE,
        Permissions::USERS_MANAGE_ROLES,
        
        // Full access to company modules
        ...Permissions::getAllByModule()['acct.customers'],
        ...Permissions::getAllByModule()['acct.invoices'],
        ...Permissions::getAllByModule()['acct.payments'],
        ...Permissions::getAllByModule()['acct.allocations'],
        
        // Reporting access
        Permissions::REPORTING_DASHBOARD_VIEW,
        Permissions::REPORTING_REPORTS_VIEW,
        Permissions::REPORTING_REPORTS_GENERATE,
        
        // Commands
        Permissions::COMMANDS_VIEW_HISTORY,
        
        // Required system permissions
        Permissions::RLS_CONTEXT,
    ],
    
    'accountant' => [
        // Accounting full access
        ...Permissions::getAllByModule()['acct.customers'],
        ...Permissions::getAllByModule()['acct.invoices'],
        ...Permissions::getAllByModule()['acct.payments'],
        ...Permissions::getAllByModule()['acct.allocations'],
        
        // Ledger management
        ...Permissions::getAllByModule()['ledger.entries'],
        ...Permissions::getAllByModule()['ledger.accounts'],
        Permissions::LEDGER_PERIOD_CLOSE_VIEW,
        Permissions::LEDGER_RECONCILIATION_VIEW,
        Permissions::LEDGER_RECONCILIATION_CREATE,
        Permissions::LEDGER_RECONCILIATION_UPDATE,
        
        // Reporting access
        Permissions::REPORTING_DASHBOARD_VIEW,
        Permissions::REPORTING_REPORTS_VIEW,
        Permissions::REPORTING_REPORTS_GENERATE,
        
        // Commands
        Permissions::COMMANDS_EXECUTE,
        Permissions::COMMANDS_VIEW_HISTORY,
        
        // Required system permissions
        Permissions::RLS_CONTEXT,
    ],
    
    'viewer' => [
        // Read-only access
        Permissions::ACCT_CUSTOMERS_VIEW,
        Permissions::ACCT_INVOICES_VIEW,
        Permissions::ACCT_PAYMENTS_VIEW,
        Permissions::ACCT_ALLOCATIONS_VIEW,
        Permissions::LEDGER_ENTRIES_VIEW,
        Permissions::LEDGER_ACCOUNTS_VIEW,
        Permissions::REPORTING_DASHBOARD_VIEW,
        Permissions::REPORTING_REPORTS_VIEW,
        
        // Required system permissions
        Permissions::RLS_CONTEXT,
    ],
];
