<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\Umrah\Models\Agent;
use App\Services\CompanyContextService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates the HTTP payload that becomes a CreateTicketBooking command.
 * Plan B left the permission check for Plan C to add once
 * UMRAH_TICKET_CREATE existed -- it does now (Task 1), so `authorize()`
 * checks it here alongside RLS context.
 */
class StoreTicketBookingRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->validateRlsContext()
            && $this->hasCompanyPermission(Permissions::UMRAH_TICKET_CREATE);
    }

    public function rules(): array
    {
        $companyId = app(CompanyContextService::class)->getCompanyId();

        return [
            'customer_id' => [
                'required', 'uuid',
                // Rule::exists() splits its first argument on the first "."
                // to pick a database *connection*, not a schema. Passing
                // 'acct.customers' resolves to a real 'acct' connection
                // (config/database.php), but that is a genuinely separate
                // PDO connection/session from the default 'pgsql' one this
                // request already set RLS context on -- so the query runs
                // with no app.current_company_id set and RLS silently
                // filters out every row, failing validation for a valid
                // customer. Passing the model class instead makes
                // parseTable() read the table and connection off the model
                // (`acct.customers` on the default `pgsql` connection),
                // which shares the RLS context already established.
                Rule::exists(Customer::class, 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'agent_id' => [
                'nullable', 'uuid',
                // Same reasoning as customer_id above -- there is also no
                // 'umrah' connection configured at all, so
                // Rule::exists('umrah.agents', ...) would additionally fail
                // outright with "Database connection [umrah] not configured."
                Rule::exists(Agent::class, 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'supplier_vendor_id' => [
                'required', 'uuid',
                // Same reasoning as customer_id above.
                Rule::exists(Vendor::class, 'id')->where(fn ($q) => $q
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
            $companyId = app(CompanyContextService::class)->getCompanyId();
            $agentId = $this->input('agent_id');
            $customerId = $this->input('customer_id');

            // The command enforces booking.customer_id === agent.customer_id
            // and throws when it does not; a mismatch belongs here, as a
            // field error the form renders inline, not as an exception the
            // command surfaces as a 500.
            if ($agentId && $customerId && $companyId) {
                $agentCustomerId = DB::table('umrah.agents')
                    ->where('id', $agentId)
                    ->where('company_id', $companyId)
                    ->value('customer_id');

                if ($agentCustomerId !== null && $agentCustomerId !== $customerId) {
                    $validator->errors()->add(
                        'agent_id',
                        'This agent is linked to a different customer than the one selected as buyer.'
                    );
                }
            }

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
