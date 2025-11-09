<?php

namespace Modules\Accounting\Domain\Customers\Services;

use App\Models\Company;
use App\Models\Customer;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class CustomerQueryService
{
    /**
     * Get paginated list of customers for a company.
     */
    public function getCustomers(Company $company, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Customer::where('company_id', $company->id);

        // Apply filters
        if (! empty($filters['status'])) {
            $query->withStatus($filters['status']);
        }

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (! empty($filters['currency'])) {
            $query->where('currency', $filters['currency']);
        }

        // Order by most recent first
        $query->orderBy('created_at', 'desc');

        return $query->paginate($perPage);
    }

    /**
     * Get customer details with relationships.
     */
    public function getCustomerDetails(Company $company, string $customerId): ?Customer
    {
        return Customer::where('company_id', $company->id)
            ->where('id', $customerId)
            ->with([
                'contacts',
                'addresses',
                'creditLimits' => function ($query) {
                    $query->orderBy('effective_at', 'desc');
                },
                'createdBy',
                'invoices' => function ($query) {
                    $query->where('status', '!=', 'paid')
                        ->orderBy('invoice_date', 'desc')
                        ->limit(10);
                },
            ])
            ->first();
    }

    /**
     * Search customers across multiple fields.
     */
    public function searchCustomers(Company $company, string $term, int $limit = 50): Collection
    {
        return Customer::where('company_id', $company->id)
            ->search($term)
            ->limit($limit)
            ->get(['id', 'customer_number', 'name', 'email', 'status', 'default_currency']);
    }

    /**
     * Get customers by status.
     */
    public function getCustomersByStatus(Company $company, string $status): Collection
    {
        return Customer::where('company_id', $company->id)
            ->withStatus($status)
            ->orderBy('name')
            ->get(['id', 'customer_number', 'name', 'email', 'status']);
    }

    /**
     * Get customer statistics for a company.
     */
    public function getCustomerStatistics(Company $company): array
    {
        $stats = Customer::where('company_id', $company->id)
            ->selectRaw('
                COUNT(*) as total_customers,
                COUNT(CASE WHEN status = \'active\' THEN 1 END) as active_customers,
                COUNT(CASE WHEN status = \'inactive\' THEN 1 END) as inactive_customers,
                COUNT(CASE WHEN status = \'blocked\' THEN 1 END) as blocked_customers,
                COALESCE(SUM(credit_limit), 0) as total_credit_limit,
                COUNT(CASE WHEN email IS NOT NULL AND email != \'\' THEN 1 END) as customers_with_email
            ')
            ->first();

        return [
            'total_customers' => (int) $stats->total_customers,
            'active_customers' => (int) $stats->active_customers,
            'inactive_customers' => (int) $stats->inactive_customers,
            'blocked_customers' => (int) $stats->blocked_customers,
            'total_credit_limit' => (float) $stats->total_credit_limit,
            'customers_with_email' => (int) $stats->customers_with_email,
        ];
    }

    /**
     * Get customers with credit limits.
     */
    public function getCustomersWithCreditLimits(Company $company): Collection
    {
        return Customer::where('company_id', $company->id)
            ->whereNotNull('credit_limit')
            ->where('credit_limit', '>', 0)
            ->with(['currentCreditLimit'])
            ->orderBy('credit_limit', 'desc')
            ->get();
    }

    /**
     * Check if customer number is unique within company.
     */
    public function isCustomerNumberUnique(Company $company, string $customerNumber, ?string $excludeId = null): bool
    {
        $query = Customer::where('company_id', $company->id)
            ->where('customer_number', $customerNumber);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return ! $query->exists();
    }

    /**
     * Check if email is unique within company.
     */
    public function isEmailUnique(Company $company, string $email, ?string $excludeId = null): bool
    {
        $query = Customer::where('company_id', $company->id)
            ->where('email', $email);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return ! $query->exists();
    }

    /**
     * Generate next customer number for company.
     */
    public function generateNextCustomerNumber(Company $company): string
    {
        $lastNumber = Customer::where('company_id', $company->id)
            ->where('customer_number', 'LIKE', 'CUST-%')
            ->orderByRaw('CAST(SUBSTRING(customer_number, 6) AS INTEGER) DESC')
            ->value('customer_number');

        if ($lastNumber) {
            $sequence = (int) str_replace('CUST-', '', $lastNumber) + 1;
        } else {
            $sequence = 1;
        }

        return 'CUST-'.str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get customers with outstanding balances.
     */
    public function getCustomersWithOutstandingBalances(Company $company): Collection
    {
        return Customer::where('company_id', $company->id)
            ->whereHas('invoices', function ($query) {
                $query->where('status', '!=', 'paid');
            })
            ->with(['invoices' => function ($query) {
                $query->where('status', '!=', 'paid');
            }])
            ->get()
            ->map(function ($customer) {
                $customer->balance_due = $customer->invoices->sum('balance_due');

                return $customer;
            })
            ->sortByDesc('balance_due');
    }

    /**
     * Export customers data.
     */
    public function exportCustomers(Company $company, array $filters = []): Collection
    {
        $query = Customer::where('company_id', $company->id);

        // Apply same filters as getCustomers
        if (! empty($filters['status'])) {
            $query->withStatus($filters['status']);
        }

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        return $query->orderBy('name')->get();
    }
}
