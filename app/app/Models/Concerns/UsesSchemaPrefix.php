<?php

namespace App\Models\Concerns;

trait UsesSchemaPrefix
{
    /**
     * Boot the trait.
     *
     * @return void
     */
    public static function bootUsesSchemaPrefix()
    {
        // Set the table property when the model boots
        static::booting(function ($model) {
            if (!isset($model->table) || !str_contains($model->table ?? '', '.')) {
                $model->table = $model->getSchemaTable();
            }
        });
    }

    /**
     * Get the schema-prefixed table name.
     *
     * @return string
     */
    protected function getSchemaTable(): string
    {
        // Get the base table name if not set
        $baseTable = $this->table ?? $this->getBaseTableName();

        // If already has schema, return as is
        if (str_contains($baseTable, '.')) {
            return $baseTable;
        }

        // Get schema from mapping
        $schema = $this->getTableSchema();

        return $schema ? "{$schema}.{$baseTable}" : $baseTable;
    }

    /**
     * Get the base table name without schema.
     *
     * @return string
     */
    protected function getBaseTableName(): string
    {
        return str_replace(
            ['auth.', 'public.', 'hrm.', 'acct.'],
            '',
            $this->table ?? strtolower(class_basename($this)) . 's'
        );
    }

    /**
     * Get the database schema for this model.
     *
     * @return string|null
     */
    protected function getTableSchema(): ?string
    {
        $schemaMapping = [
            // Auth schema tables
            'users' => 'auth',
            'companies' => 'auth',
            'company_invitations' => 'auth',
            'company_user' => 'auth',
            'company_secondary_currencies' => 'auth',
            'user_settings' => 'auth',
            'sessions' => 'auth',
            'password_reset_tokens' => 'auth',
            'permissions' => 'auth',
            'roles' => 'auth',
            'model_has_permissions' => 'auth',
            'model_has_roles' => 'auth',
            'role_has_permissions' => 'auth',

            // Public schema tables (reference data)
            'currencies' => 'public',
            'countries' => 'public',
            'exchange_rates' => 'public',
            'languages' => 'public',
            'locales' => 'public',
            'cache' => 'public',
            'cache_locks' => 'public',
            'jobs' => 'public',
            'job_batches' => 'public',
            'failed_jobs' => 'public',
            'idempotency_keys' => 'public',

            // HRM schema tables
            'customers' => 'hrm',
            'contacts' => 'hrm',
            'vendors' => 'hrm',
            'interactions' => 'hrm',

            // Accounting schema tables
            'invoices' => 'acct',
            'invoice_items' => 'acct',
            'invoice_item_taxes' => 'acct',
            'payments' => 'acct',
            'payment_allocations' => 'acct',
            'accounts_receivable' => 'acct',
            'accounts_payable' => 'acct',
            'ledger_accounts' => 'acct',
            'journal_entries' => 'acct',
            'journal_lines' => 'acct',
            'transactions' => 'acct',
            'items' => 'acct',
            'item_categories' => 'acct',
            'stock_movements' => 'acct',
            'bills' => 'acct',
            'bill_items' => 'acct',
            'bill_payments' => 'acct',
            'fiscal_years' => 'acct',
            'accounting_periods' => 'acct',
            'chart_of_accounts' => 'acct',
            'user_accounts' => 'acct',
            'audit_logs' => 'acct',
        ];

        return $schemaMapping[$baseTable] ?? null;
    }
}