<?php

namespace App\Constants;

/**
 * Standardized permissions for RBAC system.
 * 
 * Pattern: {module}.{resource}.{action}
 * Example: acct.customers.create, ledger.entries.view
 */
final class Permissions
{
    // System-wide permissions
    public const SYSTEM_ADMIN = 'system.admin';
    public const SYSTEM_AUDIT = 'system.audit';
    public const RLS_CONTEXT = 'rls.context';
    
    // Company management permissions
    public const COMPANIES_VIEW = 'companies.view';
    public const COMPANIES_CREATE = 'companies.create';
    public const COMPANIES_UPDATE = 'companies.update';
    public const COMPANIES_DELETE = 'companies.delete';
    public const COMPANIES_MANAGE_USERS = 'companies.manage_users';
    
    // User management permissions
    public const USERS_VIEW = 'users.view';
    public const USERS_CREATE = 'users.create';
    public const USERS_UPDATE = 'users.update';
    public const USERS_DELETE = 'users.delete';
    public const USERS_MANAGE_ROLES = 'users.manage_roles';
    
    // Accounting module permissions
    public const ACCT_CUSTOMERS_VIEW = 'acct.customers.view';
    public const ACCT_CUSTOMERS_CREATE = 'acct.customers.create';
    public const ACCT_CUSTOMERS_UPDATE = 'acct.customers.update';
    public const ACCT_CUSTOMERS_DELETE = 'acct.customers.delete';
    public const ACCT_CUSTOMERS_MANAGE_CREDIT = 'acct.customers.manage_credit';
    
    public const ACCT_INVOICES_VIEW = 'acct.invoices.view';
    public const ACCT_INVOICES_CREATE = 'acct.invoices.create';
    public const ACCT_INVOICES_UPDATE = 'acct.invoices.update';
    public const ACCT_INVOICES_DELETE = 'acct.invoices.delete';
    public const ACCT_INVOICES_VOID = 'acct.invoices.void';
    public const ACCT_INVOICES_APPROVE = 'acct.invoices.approve';
    
    public const ACCT_PAYMENTS_VIEW = 'acct.payments.view';
    public const ACCT_PAYMENTS_CREATE = 'acct.payments.create';
    public const ACCT_PAYMENTS_UPDATE = 'acct.payments.update';
    public const ACCT_PAYMENTS_DELETE = 'acct.payments.delete';
    public const ACCT_PAYMENTS_VOID = 'acct.payments.void';
    public const ACCT_PAYMENTS_PROCESS_BATCH = 'acct.payments.process_batch';
    
    public const ACCT_ALLOCATIONS_VIEW = 'acct.allocations.view';
    public const ACCT_ALLOCATIONS_CREATE = 'acct.allocations.create';
    public const ACCT_ALLOCATIONS_UPDATE = 'acct.allocations.update';
    public const ACCT_ALLOCATIONS_DELETE = 'acct.allocations.delete';
    
    // Ledger module permissions
    public const LEDGER_ENTRIES_VIEW = 'ledger.entries.view';
    public const LEDGER_ENTRIES_CREATE = 'ledger.entries.create';
    public const LEDGER_ENTRIES_UPDATE = 'ledger.entries.update';
    public const LEDGER_ENTRIES_DELETE = 'ledger.entries.delete';
    public const LEDGER_ENTRIES_POST = 'ledger.entries.post';
    
    public const LEDGER_ACCOUNTS_VIEW = 'ledger.accounts.view';
    public const LEDGER_ACCOUNTS_CREATE = 'ledger.accounts.create';
    public const LEDGER_ACCOUNTS_UPDATE = 'ledger.accounts.update';
    public const LEDGER_ACCOUNTS_DELETE = 'ledger.accounts.delete';
    
    public const LEDGER_PERIOD_CLOSE_VIEW = 'ledger.period_close.view';
    public const LEDGER_PERIOD_CLOSE_EXECUTE = 'ledger.period_close.execute';
    public const LEDGER_PERIOD_CLOSE_REOPEN = 'ledger.period_close.reopen';
    
    public const LEDGER_RECONCILIATION_VIEW = 'ledger.reconciliation.view';
    public const LEDGER_RECONCILIATION_CREATE = 'ledger.reconciliation.create';
    public const LEDGER_RECONCILIATION_UPDATE = 'ledger.reconciliation.update';
    public const LEDGER_RECONCILIATION_APPROVE = 'ledger.reconciliation.approve';
    
    // Reporting module permissions
    public const REPORTING_DASHBOARD_VIEW = 'reporting.dashboard.view';
    public const REPORTING_DASHBOARD_REFRESH = 'reporting.dashboard.refresh';
    
    public const REPORTING_REPORTS_VIEW = 'reporting.reports.view';
    public const REPORTING_REPORTS_GENERATE = 'reporting.reports.generate';
    public const REPORTING_REPORTS_EXPORT = 'reporting.reports.export';
    public const REPORTING_REPORTS_SCHEDULE = 'reporting.reports.schedule';
    
    // Command execution permissions
    public const COMMANDS_EXECUTE = 'commands.execute';
    public const COMMANDS_VIEW_HISTORY = 'commands.view_history';
    public const COMMANDS_MANAGE = 'commands.manage';
    
    // Audit and compliance permissions
    public const AUDIT_ENTRIES_VIEW = 'audit.entries.view';
    public const AUDIT_ENTRIES_EXPORT = 'audit.entries.export';
    public const COMPLIANCE_REPORTS_VIEW = 'compliance.reports.view';
    public const COMPLIANCE_REPORTS_GENERATE = 'compliance.reports.generate';
    
    // Hospitality module permissions (when implemented)
    public const HSP_RESERVATIONS_VIEW = 'hsp.reservations.view';
    public const HSP_RESERVATIONS_CREATE = 'hsp.reservations.create';
    public const HSP_RESERVATIONS_UPDATE = 'hsp.reservations.update';
    public const HSP_RESERVATIONS_CANCEL = 'hsp.reservations.cancel';
    
    // CRM module permissions (when implemented)
    public const CRM_CONTACTS_VIEW = 'crm.contacts.view';
    public const CRM_CONTACTS_CREATE = 'crm.contacts.create';
    public const CRM_CONTACTS_UPDATE = 'crm.contacts.update';
    public const CRM_CONTACTS_DELETE = 'crm.contacts.delete';
    
    /**
     * Get all permissions grouped by module.
     */
    public static function getAllByModule(): array
    {
        return [
            'system' => [
                self::SYSTEM_ADMIN,
                self::SYSTEM_AUDIT,
                self::RLS_CONTEXT,
            ],
            'companies' => [
                self::COMPANIES_VIEW,
                self::COMPANIES_CREATE,
                self::COMPANIES_UPDATE,
                self::COMPANIES_DELETE,
                self::COMPANIES_MANAGE_USERS,
            ],
            'users' => [
                self::USERS_VIEW,
                self::USERS_CREATE,
                self::USERS_UPDATE,
                self::USERS_DELETE,
                self::USERS_MANAGE_ROLES,
            ],
            'acct.customers' => [
                self::ACCT_CUSTOMERS_VIEW,
                self::ACCT_CUSTOMERS_CREATE,
                self::ACCT_CUSTOMERS_UPDATE,
                self::ACCT_CUSTOMERS_DELETE,
                self::ACCT_CUSTOMERS_MANAGE_CREDIT,
            ],
            'acct.invoices' => [
                self::ACCT_INVOICES_VIEW,
                self::ACCT_INVOICES_CREATE,
                self::ACCT_INVOICES_UPDATE,
                self::ACCT_INVOICES_DELETE,
                self::ACCT_INVOICES_VOID,
                self::ACCT_INVOICES_APPROVE,
            ],
            'acct.payments' => [
                self::ACCT_PAYMENTS_VIEW,
                self::ACCT_PAYMENTS_CREATE,
                self::ACCT_PAYMENTS_UPDATE,
                self::ACCT_PAYMENTS_DELETE,
                self::ACCT_PAYMENTS_VOID,
                self::ACCT_PAYMENTS_PROCESS_BATCH,
            ],
            'acct.allocations' => [
                self::ACCT_ALLOCATIONS_VIEW,
                self::ACCT_ALLOCATIONS_CREATE,
                self::ACCT_ALLOCATIONS_UPDATE,
                self::ACCT_ALLOCATIONS_DELETE,
            ],
            'ledger.entries' => [
                self::LEDGER_ENTRIES_VIEW,
                self::LEDGER_ENTRIES_CREATE,
                self::LEDGER_ENTRIES_UPDATE,
                self::LEDGER_ENTRIES_DELETE,
                self::LEDGER_ENTRIES_POST,
            ],
            'ledger.accounts' => [
                self::LEDGER_ACCOUNTS_VIEW,
                self::LEDGER_ACCOUNTS_CREATE,
                self::LEDGER_ACCOUNTS_UPDATE,
                self::LEDGER_ACCOUNTS_DELETE,
            ],
            'ledger.period_close' => [
                self::LEDGER_PERIOD_CLOSE_VIEW,
                self::LEDGER_PERIOD_CLOSE_EXECUTE,
                self::LEDGER_PERIOD_CLOSE_REOPEN,
            ],
            'ledger.reconciliation' => [
                self::LEDGER_RECONCILIATION_VIEW,
                self::LEDGER_RECONCILIATION_CREATE,
                self::LEDGER_RECONCILIATION_UPDATE,
                self::LEDGER_RECONCILIATION_APPROVE,
            ],
            'reporting.dashboard' => [
                self::REPORTING_DASHBOARD_VIEW,
                self::REPORTING_DASHBOARD_REFRESH,
            ],
            'reporting.reports' => [
                self::REPORTING_REPORTS_VIEW,
                self::REPORTING_REPORTS_GENERATE,
                self::REPORTING_REPORTS_EXPORT,
                self::REPORTING_REPORTS_SCHEDULE,
            ],
            'commands' => [
                self::COMMANDS_EXECUTE,
                self::COMMANDS_VIEW_HISTORY,
                self::COMMANDS_MANAGE,
            ],
            'audit' => [
                self::AUDIT_ENTRIES_VIEW,
                self::AUDIT_ENTRIES_EXPORT,
            ],
            'compliance' => [
                self::COMPLIANCE_REPORTS_VIEW,
                self::COMPLIANCE_REPORTS_GENERATE,
            ],
            'hsp' => [
                self::HSP_RESERVATIONS_VIEW,
                self::HSP_RESERVATIONS_CREATE,
                self::HSP_RESERVATIONS_UPDATE,
                self::HSP_RESERVATIONS_CANCEL,
            ],
            'crm' => [
                self::CRM_CONTACTS_VIEW,
                self::CRM_CONTACTS_CREATE,
                self::CRM_CONTACTS_UPDATE,
                self::CRM_CONTACTS_DELETE,
            ],
        ];
    }
    
    /**
     * Get all permissions as a flat array.
     */
    public static function getAll(): array
    {
        $reflection = new \ReflectionClass(self::class);
        return array_values($reflection->getConstants());
    }
}
