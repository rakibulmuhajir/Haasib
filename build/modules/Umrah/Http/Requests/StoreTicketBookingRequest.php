<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Http\Requests\BaseFormRequest;
use App\Services\CompanyContextService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates the HTTP payload that becomes a CreateTicketBooking command.
 * Permissions are Plan C's concern (UMRAH_TICKET_CREATE does not exist
 * yet) -- this request only guards RLS context and shape/ownership of
 * the payload, the same split BaseFormRequest already makes between
 * `validateRlsContext()` and `hasCompanyPermission()`.
 */
class StoreTicketBookingRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->validateRlsContext();
    }

    public function rules(): array
    {
        $companyId = app(CompanyContextService::class)->getCompanyId();

        return [
            'customer_id' => [
                'required', 'uuid',
                Rule::exists('acct.customers', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'agent_id' => [
                'nullable', 'uuid',
                Rule::exists('umrah.agents', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'supplier_vendor_id' => [
                'required', 'uuid',
                Rule::exists('acct.vendors', 'id')->where(fn ($q) => $q
                    ->where('company_id', $companyId)
                    ->where('is_active', true)),
            ],
            'booking_date' => ['required', 'date'],
            'pnr' => ['nullable', 'string', 'max:16'],
            'idempotency_key' => ['required', 'string', 'max:64'],

            'tickets' => ['required', 'array', 'min:1'],
            'tickets.*.passenger_name' => ['required', 'string', 'max:120'],
            'tickets.*.passport_number' => ['nullable', 'string', 'max:32'],
            'tickets.*.airline' => ['nullable', 'string', 'max:80'],
            'tickets.*.route' => ['nullable', 'string', 'max:120'],
            'tickets.*.travel_date' => ['nullable', 'date'],
            'tickets.*.gross_fare' => ['required', 'numeric', 'min:0'],
            'tickets.*.taxes' => ['nullable', 'numeric', 'min:0'],
            'tickets.*.discount' => ['nullable', 'numeric', 'min:0'],
            'tickets.*.service_fee' => ['nullable', 'numeric', 'min:0'],
            'tickets.*.supplier_cost' => ['required', 'numeric', 'min:0'],
            'tickets.*.sale_currency' => ['required', 'string', 'size:3', 'uppercase'],
            'tickets.*.sale_exchange_rate' => ['nullable', 'numeric', 'min:0.00000001'],
            'tickets.*.supplier_currency' => ['required', 'string', 'size:3', 'uppercase'],
            'tickets.*.supplier_exchange_rate' => ['nullable', 'numeric', 'min:0.00000001'],
        ];
    }

    /**
     * A wildcard rule cannot compare one ticket's own currency field to
     * the company base currency, so the "rate required when currency
     * differs from base" check runs here instead, per ticket.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $baseCurrency = app(CompanyContextService::class)->getCompany()?->base_currency;

            if (! $baseCurrency) {
                return;
            }

            foreach ((array) $this->input('tickets', []) as $index => $ticket) {
                if (($ticket['sale_currency'] ?? null) !== $baseCurrency && empty($ticket['sale_exchange_rate'])) {
                    $validator->errors()->add(
                        "tickets.{$index}.sale_exchange_rate",
                        'A sale exchange rate is required when the sale currency differs from the company base currency.'
                    );
                }

                if (($ticket['supplier_currency'] ?? null) !== $baseCurrency && empty($ticket['supplier_exchange_rate'])) {
                    $validator->errors()->add(
                        "tickets.{$index}.supplier_exchange_rate",
                        'A supplier exchange rate is required when the supplier currency differs from the company base currency.'
                    );
                }
            }
        });
    }
}
