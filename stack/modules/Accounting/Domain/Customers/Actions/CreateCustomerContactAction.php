<?php

namespace Modules\Accounting\Domain\Customers\Actions;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Domain\Customers\DTOs\CustomerContactData;
use Modules\Accounting\Domain\Customers\Models\CustomerContact;

class CreateCustomerContactAction
{
    /**
     * Create a new customer contact.
     */
    public function execute(Customer $customer, CustomerContactData $data): CustomerContact
    {
        // Validate data
        $this->validate($data, $customer);

        return DB::transaction(function () use ($customer, $data) {
            // Check for email uniqueness within the customer
            if (! CustomerContact::isEmailUniqueForCustomer($customer, $data->email)) {
                throw ValidationException::withMessages([
                    'email' => 'A contact with this email already exists for this customer.',
                ]);
            }

            // Handle primary contact logic
            if ($data->is_primary) {
                // Unset existing primary contact for the same role
                CustomerContact::where('customer_id', $customer->id)
                    ->where('role', $data->role)
                    ->update(['is_primary' => false]);
            }

            // Create the contact
            $contact = CustomerContact::create([
                'customer_id' => $customer->id,
                'company_id' => $customer->company_id,
                'first_name' => $data->first_name,
                'last_name' => $data->last_name,
                'email' => $data->email,
                'phone' => $data->phone,
                'role' => $data->role,
                'is_primary' => $data->is_primary,
                'preferred_channel' => $data->preferred_channel,
                'created_by_user_id' => auth()->id(),
            ]);

            // Emit audit event
            $this->emitAuditEvent('customer_contact_created', $contact);

            return $contact;
        });
    }

    /**
     * Validate the contact data.
     */
    private function validate(CustomerContactData $data, Customer $customer): void
    {
        $validator = Validator::make((array) $data, [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'role' => 'required|string|max:100',
            'is_primary' => 'boolean',
            'preferred_channel' => 'required|in:email,phone,sms,portal',
        ], [
            'email.required' => 'Email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'A contact with this email already exists for this customer.',
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
            'role.required' => 'Contact role is required.',
            'preferred_channel.required' => 'Preferred communication channel is required.',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }
    }

    /**
     * Emit audit event for the action.
     */
    private function emitAuditEvent(string $event, CustomerContact $contact): void
    {
        // Integration with existing audit system
        if (function_exists('audit_log')) {
            audit_log($event, [
                'contact_id' => $contact->id,
                'customer_id' => $contact->customer_id,
                'company_id' => $contact->company_id,
                'email' => $contact->email,
                'role' => $contact->role,
                'is_primary' => $contact->is_primary,
                'performed_by' => auth()->id(),
            ]);
        }
    }
}
