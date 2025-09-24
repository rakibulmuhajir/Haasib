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
        ?string $currencyId = null,
        ?int $creditLimit = 0,
        ?int $paymentTerms = 30,
        ?string $notes = null,
        ?bool $isActive = true,
        ?array $metadata = null,
        ?string $idempotencyKey = null
    ): Customer {
        try {
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
        } catch (\Throwable $e) {
            // Re-throw the exception to be handled by the controller
            throw $e;
        }
    }

    /**
     * Update the specified customer in storage.
     */
    public function updateCustomer(
        Customer $customer,
        ?string $name = null,
        ?string $customerType = null,
        ?string $email = null,
        ?string $phone = null,
        ?string $website = null,
        ?array $address = null,
        ?string $currencyId = null,
        ?string $taxId = null,
        ?bool $taxExempt = null,
        ?string $paymentTerms = null,
        ?float $creditLimit = null,
        ?string $customerNumber = null,
        ?string $status = null,
        ?string $notes = null,
        ?array $primaryContact = null,
        ?string $idempotencyKey = null
    ): Customer {
        return DB::transaction(function () use (
            $customer,
            $name,
            $email,
            $phone,
            $taxId,
            $address,
            $currencyId,
            $customerType,
            $website,
            $paymentTerms,
            $creditLimit,
            $customerNumber,
            $status,
            $notes,
            $taxExempt,
            $primaryContact
        ) {
            $updateData = array_filter([
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'tax_number' => $taxId,
                'currency_id' => $currencyId,
                'status' => $status,
                'website' => $website,
                'customer_type' => $customerType,
                'customer_number' => $customerNumber,
                'payment_terms' => $paymentTerms,
                'credit_limit' => $creditLimit,
                'tax_exempt' => $taxExempt,
                'notes' => $notes,
            ], fn ($value) => $value !== null && $value !== '');

            // Only update billing_address if address data is provided
            if ($address) {
                $billingAddress = is_array($customer->billing_address)
                    ? $customer->billing_address
                    : json_decode($customer->billing_address ?: '{}', true);

                $billingAddress = array_filter([
                    'address_line_1' => $address['address_line_1'] ?? $billingAddress['address_line_1'] ?? null,
                    'address_line_2' => $address['address_line_2'] ?? $billingAddress['address_line_2'] ?? null,
                    'city' => $address['city'] ?? $billingAddress['city'] ?? null,
                    'state_province' => $address['state_province'] ?? $billingAddress['state_province'] ?? null,
                    'postal_code' => $address['postal_code'] ?? $billingAddress['postal_code'] ?? null,
                    'country_id' => $address['country_id'] ?? $billingAddress['country_id'] ?? null,
                ], fn ($value) => $value !== null && $value !== '');

                $updateData['billing_address'] = ! empty($billingAddress) ? json_encode($billingAddress) : null;
            }

            // Handle primary contact if provided
            if ($primaryContact) {
                // Primary contact handling logic would go here
                // For now, we'll log it for future implementation
                Log::info('Primary contact update requested', [
                    'customer_id' => $customer->id,
                    'primary_contact' => $primaryContact,
                ]);
            }

            $customer->update($updateData);

            Log::info('Customer updated successfully', [
                'customer_id' => $customer->id,
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
     * Generate a unique customer number using UUID.
     */
    public function generateNextCustomerNumber(int|string $companyId): string
    {
        // Use UUID with a prefix for better readability while maintaining uniqueness
        // The companyId parameter is kept for potential future use but not needed for UUID generation
        return 'CUST-'.Str::uuid()->toString();
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
