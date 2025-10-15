<?php

namespace Modules\Accounting\Domain\Customers\Actions;

use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Domain\Customers\Exceptions\CustomerUpdateException;
use Modules\Accounting\Domain\Customers\Services\CustomerQueryService;

class UpdateCustomerAction
{
    public function __construct(
        private CustomerQueryService $customerQueryService
    ) {}

    /**
     * Update an existing customer.
     */
    public function execute(Company $company, string $customerId, array $data, User $updatedBy): Customer
    {
        // Find customer
        $customer = $this->findCustomer($company, $customerId);

        // Validate input data
        $this->validateData($company, $data, $customer);

        try {
            DB::beginTransaction();

            // Store original values for audit
            $originalValues = $customer->getOriginal();

            // Update customer
            $customer->update(array_filter($data, function ($value) {
                return $value !== null;
            }));

            // Emit audit event for significant changes
            $this->emitAuditEvents($customer, $originalValues, $updatedBy);

            DB::commit();

            return $customer->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw new CustomerUpdateException('Failed to update customer: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Find customer or throw exception.
     */
    private function findCustomer(Company $company, string $customerId): Customer
    {
        $customer = Customer::where('company_id', $company->id)
            ->where('id', $customerId)
            ->first();

        if (! $customer) {
            throw new CustomerUpdateException('Customer not found');
        }

        return $customer;
    }

    /**
     * Validate customer update data.
     */
    private function validateData(Company $company, array $data, Customer $customer): void
    {
        $validator = Validator::make($data, [
            'name' => 'sometimes|required|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'customer_number' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'default_currency' => 'sometimes|required|string|size:3',
            'payment_terms' => 'nullable|string|max:100',
            'credit_limit' => 'nullable|numeric|min:0',
            'tax_id' => 'nullable|string|max:50',
            'website' => 'nullable|url|max:255',
            'notes' => 'nullable|string',
            'status' => 'nullable|in:active,inactive,blocked',
        ], [
            'name.required' => 'Customer name is required',
            'default_currency.required' => 'Default currency is required',
            'default_currency.size' => 'Currency must be a 3-character ISO code',
            'email.email' => 'Please provide a valid email address',
            'website.url' => 'Please provide a valid website URL',
            'credit_limit.min' => 'Credit limit must be a positive number or zero',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Check customer number uniqueness (excluding current customer)
        if (isset($data['customer_number']) && $data['customer_number'] !== $customer->customer_number) {
            if (! $this->customerQueryService->isCustomerNumberUnique($company, $data['customer_number'], $customer->id)) {
                throw ValidationException::withMessages([
                    'customer_number' => 'This customer number is already in use',
                ]);
            }
        }

        // Check email uniqueness within company (excluding current customer)
        if (isset($data['email']) && $data['email'] !== $customer->email) {
            if (! $this->customerQueryService->isEmailUnique($company, $data['email'], $customer->id)) {
                throw ValidationException::withMessages([
                    'email' => 'A customer with this email already exists',
                ]);
            }
        }
    }

    /**
     * Emit audit events for significant changes.
     */
    private function emitAuditEvents(Customer $customer, array $originalValues, User $updatedBy): void
    {
        $events = [];

        // Check for status change
        if ($customer->wasChanged('status')) {
            $events[] = [
                'type' => 'customer.status.changed',
                'data' => [
                    'customer_id' => $customer->id,
                    'company_id' => $customer->company_id,
                    'user_id' => $updatedBy->id,
                    'old_status' => $originalValues['status'],
                    'new_status' => $customer->status,
                ],
            ];
        }

        // Check for credit limit change
        if ($customer->wasChanged('credit_limit')) {
            $events[] = [
                'type' => 'customer.credit_limit.changed',
                'data' => [
                    'customer_id' => $customer->id,
                    'company_id' => $customer->company_id,
                    'user_id' => $updatedBy->id,
                    'old_limit' => $originalValues['credit_limit'],
                    'new_limit' => $customer->credit_limit,
                    'effective_at' => $customer->credit_limit_effective_at,
                ],
            ];
        }

        // Check for important field changes
        $importantFields = ['name', 'legal_name', 'email'];
        foreach ($importantFields as $field) {
            if ($customer->wasChanged($field)) {
                $events[] = [
                    'type' => 'customer.updated',
                    'data' => [
                        'customer_id' => $customer->id,
                        'company_id' => $customer->company_id,
                        'user_id' => $updatedBy->id,
                        'field' => $field,
                        'old_value' => $originalValues[$field],
                        'new_value' => $customer->$field,
                    ],
                ];
                break; // Only emit one general update event
            }
        }

        // Dispatch all events
        foreach ($events as $event) {
            Event::dispatch($event['type'], $event['data']);
        }
    }
}
