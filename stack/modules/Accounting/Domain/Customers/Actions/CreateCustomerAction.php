<?php

namespace Modules\Accounting\Domain\Customers\Actions;

use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Domain\Customers\Exceptions\CustomerCreationException;
use Modules\Accounting\Domain\Customers\Services\CustomerQueryService;

class CreateCustomerAction
{
    public function __construct(
        private CustomerQueryService $customerQueryService
    ) {}

    /**
     * Create a new customer.
     */
    public function execute(Company $company, array $data, User $createdBy): Customer
    {
        // Validate input data
        $this->validateData($company, $data);

        try {
            DB::beginTransaction();

            // Generate customer number if not provided
            if (empty($data['customer_number'])) {
                $data['customer_number'] = $this->customerQueryService->generateNextCustomerNumber($company);
            }

            // Create customer
            $customer = Customer::create([
                'company_id' => $company->id,
                'customer_number' => $data['customer_number'],
                'name' => $data['name'],
                'status' => $data['status'] ?? 'active',
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'currency' => $data['currency'] ?? 'USD',
                'credit_limit' => $data['credit_limit'] ?? null,
                'tax_id' => $data['tax_id'] ?? null,
                'website' => $data['website'] ?? null,
                'notes' => $data['notes'] ?? null,
                'address' => $data['address'] ?? null,
                'country' => $data['country'] ?? null,
            ]);

            // Emit audit event
            Event::dispatch('customer.created', [
                'customer_id' => $customer->id,
                'company_id' => $company->id,
                'user_id' => $createdBy->id,
                'customer_number' => $customer->customer_number,
                'name' => $customer->name,
                'email' => $customer->email,
                'status' => $customer->status,
                'credit_limit' => $customer->credit_limit,
            ]);

            DB::commit();

            return $customer;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new CustomerCreationException('Failed to create customer: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Validate customer creation data.
     */
    private function validateData(Company $company, array $data): void
    {
        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'customer_number' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'currency' => 'required|string|size:3',
            'credit_limit' => 'nullable|numeric|min:0',
            'tax_id' => 'nullable|string|max:50',
            'website' => 'nullable|url|max:255',
            'notes' => 'nullable|string',
            'status' => 'nullable|in:active,inactive,blocked',
            'address' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
        ], [
            'name.required' => 'Customer name is required',
            'currency.required' => 'Currency is required',
            'currency.size' => 'Currency must be a 3-character ISO code',
            'email.email' => 'Please provide a valid email address',
            'website.url' => 'Please provide a valid website URL',
            'credit_limit.min' => 'Credit limit must be a positive number or zero',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Check customer number uniqueness
        if (! empty($data['customer_number'])) {
            if (! $this->customerQueryService->isCustomerNumberUnique($company, $data['customer_number'])) {
                throw ValidationException::withMessages([
                    'customer_number' => 'This customer number is already in use',
                ]);
            }
        }

        // Check email uniqueness within company
        if (! empty($data['email'])) {
            if (! $this->customerQueryService->isEmailUnique($company, $data['email'])) {
                throw ValidationException::withMessages([
                    'email' => 'A customer with this email already exists',
                ]);
            }
        }
    }
}
