<?php

namespace Modules\Accounting\Domain\Customers\Actions;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Domain\Customers\DTOs\CustomerAddressData;
use Modules\Accounting\Domain\Customers\Models\CustomerAddress;

class CreateCustomerAddressAction
{
    /**
     * Create a new customer address.
     */
    public function execute(Customer $customer, CustomerAddressData $data): CustomerAddress
    {
        // Validate data
        $this->validate($data);

        return DB::transaction(function () use ($customer, $data) {
            // Handle default address logic
            if ($data->is_default) {
                // Unset existing default address for the same type
                CustomerAddress::where('customer_id', $customer->id)
                    ->where('type', $data->type)
                    ->update(['is_default' => false]);
            }

            // Create the address
            $address = CustomerAddress::create([
                'customer_id' => $customer->id,
                'company_id' => $customer->company_id,
                'label' => $data->label,
                'type' => $data->type,
                'line1' => $data->line1,
                'line2' => $data->line2,
                'city' => $data->city,
                'state' => $data->state,
                'postal_code' => $data->postal_code,
                'country' => $data->country,
                'is_default' => $data->is_default,
                'notes' => $data->notes,
            ]);

            // Emit audit event
            $this->emitAuditEvent('customer_address_created', $address);

            return $address;
        });
    }

    /**
     * Validate the address data.
     */
    private function validate(CustomerAddressData $data): void
    {
        $validator = Validator::make((array) $data, [
            'label' => 'required|string|max:100',
            'type' => 'required|in:billing,shipping,statement,other',
            'line1' => 'required|string|max:255',
            'line2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:30',
            'country' => 'required|string|size:2', // ISO 3166-1 alpha-2
            'is_default' => 'boolean',
            'notes' => 'nullable|string',
        ], [
            'label.required' => 'Address label is required.',
            'type.required' => 'Address type is required.',
            'type.in' => 'Address type must be one of: billing, shipping, statement, other.',
            'line1.required' => 'Address line 1 is required.',
            'country.required' => 'Country code is required.',
            'country.size' => 'Country code must be 2 characters (ISO 3166-1 alpha-2).',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        // Additional business logic validation
        $this->validateBusinessRules($data);
    }

    /**
     * Validate business-specific rules.
     */
    private function validateBusinessRules(CustomerAddressData $data): void
    {
        // Validate country-specific requirements
        $country = strtoupper($data->country);

        switch ($country) {
            case 'US':
                if (empty($data->city) || empty($data->state) || empty($data->postal_code)) {
                    throw ValidationException::withMessages([
                        'address' => 'US addresses require city, state, and postal code.',
                    ]);
                }
                // Validate US postal code format
                if ($data->postal_code && ! preg_match('/^\d{5}(-\d{4})?$/', $data->postal_code)) {
                    throw ValidationException::withMessages([
                        'postal_code' => 'US postal code must be in format 12345 or 12345-6789.',
                    ]);
                }
                break;

            case 'CA':
                if (empty($data->city) || empty($data->postal_code)) {
                    throw ValidationException::withMessages([
                        'address' => 'Canadian addresses require city and postal code.',
                    ]);
                }
                // Validate Canadian postal code format
                if ($data->postal_code && ! preg_match('/^[A-Z]\d[A-Z]\s?\d[A-Z]\d$/i', $data->postal_code)) {
                    throw ValidationException::withMessages([
                        'postal_code' => 'Canadian postal code must be in format A1A 1A1.',
                    ]);
                }
                break;
        }
    }

    /**
     * Emit audit event for the action.
     */
    private function emitAuditEvent(string $event, CustomerAddress $address): void
    {
        if (function_exists('audit_log')) {
            audit_log($event, [
                'address_id' => $address->id,
                'customer_id' => $address->customer_id,
                'company_id' => $address->company_id,
                'type' => $address->type,
                'country' => $address->country,
                'is_default' => $address->is_default,
                'full_address' => $address->full_address,
                'performed_by' => auth()->id(),
            ]);
        }
    }
}
