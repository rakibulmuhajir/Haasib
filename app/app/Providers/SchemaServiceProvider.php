<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;

class SchemaServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Listen for model booting events to automatically add schema prefixes
        Event::listen('booting: *', function ($model) {
            if ($model instanceof Model && !isset($model->table)) {
                $model->table = $this->getSchemaTableName($model);
            } elseif ($model instanceof Model && isset($model->table) && !str_contains($model->table, '.')) {
                // Add schema prefix if not already present
                $model->table = $this->getSchemaTableName($model, $model->table);
            }
        });
    }

    /**
     * Get the schema-prefixed table name for a model.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string|null  $baseTable
     * @return string
     */
    protected function getSchemaTableName($model, ?string $baseTable = null): string
    {
        $baseTable = $baseTable ?? $this->getBaseTableName($model);

        // If already has schema, return as is
        if (str_contains($baseTable, '.')) {
            return $baseTable;
        }

        $schema = $this->getTableSchema($baseTable);

        return $schema ? "{$schema}.{$baseTable}" : $baseTable;
    }

    /**
     * Get the base table name for a model.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return string
     */
    protected function getBaseTableName($model): string
    {
        if (isset($model->table)) {
            return str_replace(['auth.', 'public.', 'hrm.', 'acct.'], '', $model->table);
        }

        // Default: pluralize the model name
        return strtolower(class_basename($model)) . 's';
    }

    /**
     * Get the database schema for a table.
     *
     * @param  string  $table
     * @return string|null
     */
    protected function getTableSchema(string $table): ?string
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

        return $schemaMapping[$table] ?? null;
    }
}