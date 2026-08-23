<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Http\Requests\BaseFormRequest;
use App\Modules\Umrah\Models\Ticket;
use App\Services\CompanyContextService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates the HTTP payload that becomes a CancelTicket command. See
 * StoreTicketBookingRequest for the split this follows: this request
 * only guards RLS context and payload shape/ownership, not permissions
 * (Plan C's concern).
 *
 * The ticket being cancelled is a route parameter
 * (`tickets/{ticket}/cancel`), not a body field -- prepareForValidation()
 * copies it into `ticket_id` so it can be validated the same way the
 * rest of the payload is, following StoreTicketBookingRequest's own
 * note: `Rule::exists()` splits its first argument on "." to pick a
 * *connection*, not a schema, and there is no `umrah` connection
 * configured at all, so a schema-qualified string here would fail
 * outright rather than merely leak past RLS. Passing the model class
 * instead resolves the table and connection off the model, sharing the
 * RLS context this request already established.
 */
class StoreTicketCancellationRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->validateRlsContext();
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['ticket_id' => $this->route('ticket')]);
    }

    public function rules(): array
    {
        $companyId = app(CompanyContextService::class)->getCompanyId();

        return [
            'ticket_id' => [
                'required', 'uuid',
                Rule::exists(Ticket::class, 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'cancellation_date' => ['required', 'date'],
            'buyer_returns_amount' => ['required', 'numeric', 'min:0'],
            'supplier_returns_amount' => ['required', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['required', 'string', 'max:64'],
        ];
    }

    /**
     * A cancellation that hands back nothing to either side is not a
     * cancellation -- one leg must be greater than zero.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $buyer = (float) $this->input('buyer_returns_amount', 0);
            $supplier = (float) $this->input('supplier_returns_amount', 0);

            if ($buyer <= 0.0 && $supplier <= 0.0) {
                $validator->errors()->add(
                    'buyer_returns_amount',
                    'At least one of the buyer or supplier returns must be greater than zero.'
                );
            }
        });
    }
}
