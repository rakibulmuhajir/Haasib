<?php

namespace Modules\Accounting\Domain\Customers\Actions;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Domain\Customers\DTOs\CustomerContactData;
use Modules\Accounting\Domain\Customers\Models\CustomerContact;

class UpdateCustomerContactAction
{
    /**
     * Update an existing customer contact.
     */
    public function execute(CustomerContact $contact, CustomerContactData $data): CustomerContact
    {
        // Validate data
        $this->validate($data);

        return DB::transaction(function () use ($contact, $data) {
            // Check for email uniqueness (excluding current contact)
            if (! CustomerContact::isEmailUniqueForCustomer($contact->customer, $data->email, $contact)) {
                throw ValidationException::withMessages([
                    'email' => 'A contact with this email already exists for this customer.',
                ]);
            }

            // Handle primary contact logic if changing role or setting as primary
            $wasPrimary = $contact->is_primary;
            $roleChanged = $contact->role !== $data->role;
            $settingAsPrimary = $data->is_primary && ! $wasPrimary;

            if ($settingAsPrimary || ($roleChanged && $data->is_primary)) {
                // Unset existing primary contact for the role
                CustomerContact::where('customer_id', $contact->customer_id)
                    ->where('role', $data->role)
                    ->where('id', '!=', $contact->id)
                    ->update(['is_primary' => false]);
            }

            // Store old values for audit
            $oldValues = [
                'email' => $contact->email,
                'role' => $contact->role,
                'is_primary' => $contact->is_primary,
                'preferred_channel' => $contact->preferred_channel,
            ];

            // Update the contact
            $contact->update([
                'first_name' => $data->first_name,
                'last_name' => $data->last_name,
                'email' => $data->email,
                'phone' => $data->phone,
                'role' => $data->role,
                'is_primary' => $data->is_primary,
                'preferred_channel' => $data->preferred_channel,
            ]);

            // Emit audit event
            $this->emitAuditEvent('customer_contact_updated', $contact, $oldValues);

            return $contact->fresh();
        });
    }

    /**
     * Validate the contact data.
     */
    private function validate(CustomerContactData $data): void
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
    private function emitAuditEvent(string $event, CustomerContact $contact, array $oldValues): void
    {
        if (function_exists('audit_log')) {
            audit_log($event, [
                'contact_id' => $contact->id,
                'customer_id' => $contact->customer_id,
                'company_id' => $contact->company_id,
                'old_values' => $oldValues,
                'new_values' => [
                    'email' => $contact->email,
                    'role' => $contact->role,
                    'is_primary' => $contact->is_primary,
                    'preferred_channel' => $contact->preferred_channel,
                ],
                'performed_by' => auth()->id(),
            ]);
        }
    }
}
