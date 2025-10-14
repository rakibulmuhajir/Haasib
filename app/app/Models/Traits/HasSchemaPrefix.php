<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Builder;

trait HasSchemaPrefix
{
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

            // Otherwise, add schema prefix based on model mapping
            return $this->getSchemaPrefix() . $this->table;
        }

        return parent::getTable();
    }

    /**
     * Get the schema prefix for the model.
     *
     * @return string
     */
    protected function getSchemaPrefix(): string
    {
        $schemaMap = [
            // Auth schema
            'User' => 'auth.',
            'Company' => 'auth.',
            'CompanyInvitation' => 'auth.',
            'CompanySecondaryCurrency' => 'auth.',
            'UserSetting' => 'auth.',

            // Public schema (reference data)
            'Currency' => 'public.',
            'Country' => 'public.',
            'ExchangeRate' => 'public.',
            'Language' => 'public.',
            'Locale' => 'public.',

            // HRM schema
            'Customer' => 'hrm.',
            'Vendor' => 'hrm.',
            'Contact' => 'hrm.',
            'Interaction' => 'hrm.',

            // Accounting schema
            'Invoice' => 'acct.',
            'InvoiceItem' => 'acct.',
            'InvoiceItemTax' => 'acct.',
            'Payment' => 'acct.',
            'PaymentAllocation' => 'acct.',
            'AccountsReceivable' => 'acct.',
            'LedgerAccount' => 'acct.',
            'JournalEntry' => 'acct.',
            'JournalLine' => 'acct.',
            'Item' => 'acct.',
            'ItemCategory' => 'acct.',
            'StockMovement' => 'acct.',
            'Transaction' => 'acct.',
            'FiscalYear' => 'acct.',
            'AccountingPeriod' => 'acct.',
        ];

        $className = class_basename(static::class);

        return $schemaMap[$className] ?? '';
    }

    /**
     * Resolve connection using fully qualified table name.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return \Illuminate\Database\Query\Builder
     */
    protected function resolveConnection($connection)
    {
        // If table has schema prefix, ensure we're using the default connection
        if (isset($this->table) && str_contains($this->table, '.')) {
            return $this->getConnection();
        }

        return parent::resolveConnection($connection);
    }
}