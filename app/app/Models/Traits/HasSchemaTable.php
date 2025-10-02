<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Model;

trait HasSchemaTable
{
    /**
     * The model's table.
     *
     * @var string|null
     */
    protected $table;

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        if (isset($this->table)) {
            // If table already has schema prefix, return as is
            if (str_contains($this->table, '.')) {
                return $this->table;
            }

            // Add schema prefix
            return $this->getSchemaPrefix() . $this->table;
        }

        // Generate table name from class name and add schema
        $tableName = $this->inferTable();

        return $this->getSchemaPrefix() . $tableName;
    }

    /**
     * Get the schema prefix for this model's table.
     *
     * @return string
     */
    protected function getSchemaPrefix(): string
    {
        // Define which tables belong to which schemas
        $schemas = [
            // Auth schema
            'users' => 'auth.',
            'companies' => 'auth.',
            'company_invitations' => 'auth.',
            'company_user' => 'auth.',
            'company_secondary_currencies' => 'auth.',
            'user_settings' => 'auth.',
            'sessions' => 'auth.',
            'password_reset_tokens' => 'auth.',
            'permissions' => 'auth.',
            'roles' => 'auth.',
            'model_has_permissions' => 'auth.',
            'model_has_roles' => 'auth.',
            'role_has_permissions' => 'auth.',

            // Public schema (reference data)
            'currencies' => 'public.',
            'countries' => 'public.',
            'exchange_rates' => 'public.',
            'languages' => 'public.',
            'locales' => 'public.',
            'cache' => 'public.',
            'cache_locks' => 'public.',
            'jobs' => 'public.',
            'job_batches' => 'public.',
            'failed_jobs' => 'public.',
            'idempotency_keys' => 'public.',

            // HRM schema
            'customers' => 'hrm.',
            'contacts' => 'hrm.',
            'vendors' => 'hrm.',
            'interactions' => 'hrm.',

            // Accounting schema
            'invoices' => 'acct.',
            'invoice_items' => 'acct.',
            'invoice_item_taxes' => 'acct.',
            'payments' => 'acct.',
            'payment_allocations' => 'acct.',
            'accounts_receivable' => 'acct.',
            'accounts_payable' => 'acct.',
            'ledger_accounts' => 'acct.',
            'journal_entries' => 'acct.',
            'journal_lines' => 'acct.',
            'transactions' => 'acct.',
            'items' => 'acct.',
            'item_categories' => 'acct.',
            'stock_movements' => 'acct.',
            'bills' => 'acct.',
            'bill_items' => 'acct.',
            'bill_payments' => 'acct.',
            'fiscal_years' => 'acct.',
            'accounting_periods' => 'acct.',
            'chart_of_accounts' => 'acct.',
            'user_accounts' => 'acct.',
            'audit_logs' => 'acct.',
        ];

        $tableName = $this->table ?? $this->inferTable();

        return $schemas[$tableName] ?? '';
    }

    /**
     * Infer the table name from the model class.
     *
     * @return string
     */
    protected function inferTable(): string
    {
        // Special cases for irregular plurals
        $specialCases = [
            'Company' => 'companies',
            'Currency' => 'currencies',
            'Country' => 'countries',
            'Entry' => 'entries',
            'FiscalYear' => 'fiscal_years',
            'ItemCategory' => 'item_categories',
            'PaymentAllocation' => 'payment_allocations',
            'StockMovement' => 'stock_movements',
            'UserSetting' => 'user_settings',
            'InvoiceItem' => 'invoice_items',
            'InvoiceItemTax' => 'invoice_item_taxes',
            'JournalEntry' => 'journal_entries',
            'JournalLine' => 'journal_lines',
            'LedgerAccount' => 'ledger_accounts',
            'AccountsReceivable' => 'accounts_receivable',
            'AccountsPayable' => 'accounts_payable',
            'CompanyInvitation' => 'company_invitations',
            'CompanySecondaryCurrency' => 'company_secondary_currencies',
            'PasswordResetToken' => 'password_reset_tokens',
            'Interaction' => 'interactions',
            'AccountingPeriod' => 'accounting_periods',
            'ChartOfAccounts' => 'chart_of_accounts',
            'UserAccount' => 'user_accounts',
            'BillItem' => 'bill_items',
            'BillPayment' => 'bill_payments',
        ];

        $className = class_basename(static::class);

        if (isset($specialCases[$className])) {
            return $specialCases[$className];
        }

        // Default: add 's' for regular plurals
        return strtolower($className) . 's';
    }
}