<?php

namespace Modules\Accounting\Domain\Customers\Actions;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Domain\Customers\DTOs\CustomerCommunicationData;
use Modules\Accounting\Domain\Customers\Models\CustomerCommunication;
use Modules\Accounting\Domain\Customers\Models\CustomerContact;

class LogCustomerCommunicationAction
{
    /**
     * Log a new customer communication.
     */
    public function execute(Customer $customer, CustomerCommunicationData $data): CustomerCommunication
    {
        // Validate data
        $this->validate($data, $customer);

        return DB::transaction(function () use ($customer, $data) {
            // Validate contact belongs to customer if provided
            if ($data->contact_id) {
                $contact = CustomerContact::where('id', $data->contact_id)
                    ->where('customer_id', $customer->id)
                    ->first();

                if (! $contact) {
                    throw ValidationException::withMessages([
                        'contact_id' => 'Selected contact does not belong to this customer.',
                    ]);
                }
            }

            // Create the communication log
            $communication = CustomerCommunication::create([
                'customer_id' => $customer->id,
                'company_id' => $customer->company_id,
                'contact_id' => $data->contact_id,
                'channel' => $data->channel,
                'direction' => $data->direction,
                'subject' => $data->subject,
                'body' => $data->body,
                'logged_by_user_id' => auth()->id(),
                'occurred_at' => $data->occurred_at,
                'attachments' => $data->attachments,
            ]);

            // Emit audit event
            $this->emitAuditEvent('customer_communication_logged', $communication);

            return $communication;
        });
    }

    /**
     * Validate the communication data.
     */
    private function validate(CustomerCommunicationData $data, Customer $customer): void
    {
        $validator = Validator::make((array) $data, [
            'contact_id' => 'nullable|exists:pgsql.acct.customer_contacts,id',
            'channel' => 'required|in:email,phone,meeting,note',
            'direction' => 'required|in:inbound,outbound,internal',
            'subject' => 'nullable|string|max:255',
            'body' => 'required|string',
            'occurred_at' => 'required|date',
            'attachments' => 'nullable|array',
            'attachments.*.name' => 'required|string',
            'attachments.*.url' => 'required|url',
            'attachments.*.size' => 'required|integer',
        ], [
            'channel.required' => 'Communication channel is required.',
            'channel.in' => 'Channel must be one of: email, phone, meeting, note.',
            'direction.required' => 'Communication direction is required.',
            'direction.in' => 'Direction must be one of: inbound, outbound, internal.',
            'body.required' => 'Communication body is required.',
            'occurred_at.required' => 'Occurrence date and time is required.',
            'occurred_at.date' => 'Please provide a valid date and time.',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        // Business logic validation
        $this->validateBusinessRules($data);
    }

    /**
     * Validate business-specific rules.
     */
    private function validateBusinessRules(CustomerCommunicationData $data): void
    {
        // Internal notes should not have a subject (optional) but must have body
        if ($data->direction === 'internal' && empty(trim($data->body))) {
            throw ValidationException::withMessages([
                'body' => 'Internal notes must have content.',
            ]);
        }

        // Email communications should have a subject
        if ($data->channel === 'email' && empty($data->subject)) {
            throw ValidationException::withMessages([
                'subject' => 'Email communications should have a subject.',
            ]);
        }

        // Occurred_at should not be in the future
        if ($data->occurred_at > now()) {
            throw ValidationException::withMessages([
                'occurred_at' => 'Communication date cannot be in the future.',
            ]);
        }
    }

    /**
     * Emit audit event for the action.
     */
    private function emitAuditEvent(string $event, CustomerCommunication $communication): void
    {
        if (function_exists('audit_log')) {
            audit_log($event, [
                'communication_id' => $communication->id,
                'customer_id' => $communication->customer_id,
                'company_id' => $communication->company_id,
                'contact_id' => $communication->contact_id,
                'channel' => $communication->channel,
                'direction' => $communication->direction,
                'subject' => $communication->subject,
                'occurred_at' => $communication->occurred_at->toISOString(),
                'has_attachments' => $communication->has_attachments,
                'attachment_count' => $communication->attachment_count,
                'logged_by' => auth()->id(),
            ]);
        }
    }
}
