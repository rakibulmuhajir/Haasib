<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CustomerService
{
    /**
     * Create a new customer.
     */
    public function createCustomer(
        Company $company,
        string $name,
        ?string $email = null,
        ?string $phone = null,
        ?string $taxId = null,
        ?array $billingAddress = null,
        ?array $shippingAddress = null,
        ?int $currencyId = null,
        ?int $creditLimit = 0,
        ?int $paymentTerms = 30,
        ?string $notes = null,
        ?bool $isActive = true,
        ?array $metadata = null,
        ?string $idempotencyKey = null
    ): Customer {
        return DB::transaction(function () use (
            $company,
            $name,
            $email,
            $phone,
            $taxId,
            $billingAddress,
            $shippingAddress,
            $currencyId,
            $creditLimit,
            $paymentTerms,
            $notes,
            $isActive,
            $metadata,
            $idempotencyKey
        ) {
            // Check for idempotency
            if ($idempotencyKey) {
                $existingCustomer = Customer::where('company_id', $company->id)
                    ->whereJsonContains('metadata->idempotency_key', $idempotencyKey)
                    ->first();

                if ($existingCustomer) {
                    Log::info('Customer creation skipped due to idempotency key', [
                        'customer_id' => $existingCustomer->customer_id,
                        'idempotency_key' => $idempotencyKey,
                    ]);

                    return $existingCustomer;
                }
            }

            $customer = Customer::create([
                'customer_id' => (string) Str::uuid(),
                'company_id' => $company->id,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'tax_id' => $taxId,
                'billing_address' => $billingAddress,
                'shipping_address' => $shippingAddress,
                'currency_id' => $currencyId,
                'credit_limit' => $creditLimit,
                'payment_terms' => $paymentTerms,
                'notes' => $notes,
                'is_active' => $isActive,
                'metadata' => array_merge($metadata ?? [], [
                    'idempotency_key' => $idempotencyKey,
                ]),
            ]);

            Log::info('Customer created successfully', [
                'customer_id' => $customer->customer_id,
                'company_id' => $company->id,
                'name' => $name,
            ]);

            return $customer;
        });
    }

    /**
     * Update an existing customer.
     */
    public function updateCustomer(
        Customer $customer,
        ?string $name = null,
        ?string $email = null,
        ?string $phone = null,
        ?string $taxId = null,
        ?array $billingAddress = null,
        ?array $shippingAddress = null,
        ?int $currencyId = null,
        ?int $creditLimit = null,
        ?int $paymentTerms = null,
        ?string $notes = null,
        ?bool $isActive = null,
        ?array $metadata = null
    ): Customer {
        return DB::transaction(function () use (
            $customer,
            $name,
            $email,
            $phone,
            $taxId,
            $billingAddress,
            $shippingAddress,
            $currencyId,
            $creditLimit,
            $paymentTerms,
            $notes,
            $isActive,
            $metadata
        ) {
            $updateData = array_filter([
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'tax_id' => $taxId,
                'billing_address' => $billingAddress,
                'shipping_address' => $shippingAddress,
                'currency_id' => $currencyId,
                'credit_limit' => $creditLimit,
                'payment_terms' => $paymentTerms,
                'notes' => $notes,
                'is_active' => $isActive,
            ], fn ($value) => $value !== null);

            if ($metadata) {
                $updateData['metadata'] = array_merge($customer->metadata ?? [], $metadata);
            }

            $customer->update($updateData);

            Log::info('Customer updated successfully', [
                'customer_id' => $customer->customer_id,
                'updated_fields' => array_keys($updateData),
            ]);

            return $customer->fresh();
        });
    }

    /**
     * Delete a customer (soft delete).
     */
    public function deleteCustomer(Customer $customer): bool
    {
        return DB::transaction(function () use ($customer) {
            // Check if customer has any active invoices or payments
            $hasActiveRelationships = $customer->invoices()
                ->whereNotIn('status', ['paid', 'cancelled', 'void'])
                ->exists() ||
                $customer->payments()
                    ->where('status', '!=', 'void')
                    ->exists();

            if ($hasActiveRelationships) {
                throw new \Exception('Cannot delete customer with active invoices or payments');
            }

            $customer->delete();

            Log::info('Customer deleted successfully', [
                'customer_id' => $customer->customer_id,
            ]);

            return true;
        });
    }

    /**
     * Get customer statistics.
     */
    public function getCustomerStatistics(Customer $customer): array
    {
        $totalInvoices = $customer->invoices()->count();
        $paidInvoices = $customer->invoices()->where('status', 'paid')->count();
        $outstandingInvoices = $customer->invoices()
            ->whereNotIn('status', ['paid', 'cancelled', 'void'])
            ->where('balance_due', '>', 0)
            ->count();

        $totalPayments = $customer->payments()->count();
        $totalPaid = $customer->payments()->where('status', 'allocated')->sum('amount');

        return [
            'total_invoices' => $totalInvoices,
            'paid_invoices' => $paidInvoices,
            'outstanding_invoices' => $outstandingInvoices,
            'total_payments' => $totalPayments,
            'total_paid' => $totalPaid,
            'outstanding_balance' => $customer->getOutstandingBalance(),
            'available_credit' => $customer->getAvailableCredit(),
            'risk_level' => $customer->getRiskLevel(),
            'average_payment_days' => $customer->getAveragePaymentDays(),
            'overdue_invoices_count' => $customer->getOverdueInvoicesCount(),
        ];
    }

    /**
     * Get customers for a company with optional filtering.
     */
    public function getCompanyCustomers(
        Company $company,
        ?string $search = null,
        ?string $status = null,
        ?string $customerType = null,
        ?int $countryId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        string $sortBy = 'name',
        string $sortDirection = 'asc',
        int $perPage = 15
    ) {
        $query = $company->customers()->with(['currency', 'country']);

        // Apply filters
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('customer_number', 'like', "%{$search}%");
            });
        }

        if ($status) {
            $query->where('is_active', $status === 'active');
        }

        if ($customerType) {
            $query->where('customer_type', $customerType);
        }

        if ($countryId) {
            $query->where('country_id', $countryId);
        }

        if ($dateFrom) {
            $query->where('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->where('created_at', '<=', $dateTo);
        }

        // Apply sorting
        $query->orderBy($sortBy, $sortDirection);

        return $query->paginate($perPage);
    }

    /**
     * Export customers data.
     */
    public function exportCustomers(
        Company $company,
        ?string $search = null,
        ?string $status = null,
        ?string $customerType = null,
        ?int $countryId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): array {
        $query = $company->customers()->with(['currency', 'country']);

        // Apply filters (same as getCompanyCustomers)
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('customer_number', 'like', "%{$search}%");
            });
        }

        if ($status) {
            $query->where('is_active', $status === 'active');
        }

        if ($customerType) {
            $query->where('customer_type', $customerType);
        }

        if ($countryId) {
            $query->where('country_id', $countryId);
        }

        if ($dateFrom) {
            $query->where('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->where('created_at', '<=', $dateTo);
        }

        return $query->get()->map(function ($customer) {
            return [
                'Customer Number' => $customer->customer_number,
                'Name' => $customer->name,
                'Email' => $customer->email,
                'Phone' => $customer->phone,
                'Tax ID' => $customer->tax_id,
                'Customer Type' => $customer->customer_type,
                'Currency' => $customer->currency?->code,
                'Credit Limit' => $customer->credit_limit,
                'Payment Terms' => $customer->payment_terms,
                'Status' => $customer->is_active ? 'Active' : 'Inactive',
                'Outstanding Balance' => $customer->getOutstandingBalance(),
                'Available Credit' => $customer->getAvailableCredit(),
                'Risk Level' => $customer->getRiskLevel(),
                'Created At' => $customer->created_at->format('Y-m-d H:i:s'),
            ];
        })->toArray();
    }
}
