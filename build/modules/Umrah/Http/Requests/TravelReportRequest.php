<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\HotelVendor;
use App\Modules\Umrah\Models\VisaVendor;
use App\Modules\Umrah\Services\TravelAccessService;
use App\Services\CurrentCompany;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TravelReportRequest extends BaseFormRequest
{
    public const COMPANY_REPORTS = [
        'group-profitability',
        'receivable-aging',
        'vendor-aging',
        'advances',
        'transport-dispatch',
        'ticket-sales',
        'ticket-supplier-reconciliation',
        'ticket-cancellations',
    ];

    public const SELF_REPORTS = [
        'agent-statement',
        'passenger-status',
        'departure-manifest',
        'hotel-rooming',
        'voucher-control',
    ];

    public function authorize(): bool
    {
        if (! $this->validateRlsContext()) {
            return false;
        }

        $report = (string) $this->route('report');
        if (! in_array($report, [...self::COMPANY_REPORTS, ...self::SELF_REPORTS], true)) {
            return false;
        }

        if ($this->hasCompanyPermission(Permissions::UMRAH_REPORT_VIEW)) {
            return true;
        }

        return in_array($report, self::SELF_REPORTS, true)
            && $this->hasCompanyPermission(Permissions::UMRAH_REPORT_OWN_VIEW)
            && app(TravelAccessService::class)->isAgentMember(
                app(CurrentCompany::class)->get()->id,
                $this->user(),
            );
    }

    /**
     * Reports that answer "what is coming" rather than "what happened".
     *
     * A manifest, a dispatch sheet and a rooming list are worked BEFORE the
     * trip; a visa still in process is by definition not yet delivered. These
     * defaulted to the current calendar month like the financial reports did,
     * so an operator opening Transport Dispatch to arrange next week's buses
     * saw an empty page -- the filter was right and the window was wrong.
     */
    public const FORWARD_REPORTS = [
        'transport-dispatch',
        'passenger-status',
        'departure-manifest',
        'hotel-rooming',
    ];

    protected function prepareForValidation(): void
    {
        $forward = in_array((string) $this->route('report'), self::FORWARD_REPORTS, true);

        $start = $forward ? now()->startOfDay() : now()->startOfMonth();
        $end = $forward ? now()->addDays(90)->endOfDay() : now()->endOfMonth();

        $this->merge([
            'start' => $this->input('start', $start->toDateString()),
            'end' => $this->input('end', $end->toDateString()),
        ]);
    }

    public function rules(): array
    {
        $companyId = app(CurrentCompany::class)->get()->id;
        /*
         * The model class, never its table name. Laravel reads a dot in an
         * exists rule as connection.table, so 'umrah.agents' sent it looking
         * for a connection called umrah and threw before it ever ran a
         * query. Every report filtered by a party died on it -- the agent
         * filter on the statement most visibly. Given a class it takes the
         * table and the connection from the model.
         */
        $tenantExists = static fn (string $model) => Rule::exists($model, 'id')
            ->where(static fn (Builder $query) => $query->where('company_id', $companyId)->whereNull('deleted_at'));

        return [
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after_or_equal:start'],
            'agent_id' => ['nullable', 'uuid', $tenantExists(Agent::class)],
            'visa_vendor_id' => ['nullable', 'uuid', $tenantExists(VisaVendor::class)],
            'transport_vendor_id' => ['nullable', 'uuid', $tenantExists(VisaVendor::class)],
            'hotel_vendor_id' => ['nullable', 'uuid', $tenantExists(HotelVendor::class)],
            'status' => ['nullable', 'string', 'max:40'],
            'payment_status' => ['nullable', Rule::in(['paid', 'partially_paid', 'unpaid'])],
            'transaction_type' => ['nullable', Rule::in(['charge', 'allocation', 'advance', 'reversal'])],
            'vendor_type' => ['nullable', Rule::in(['visa', 'transport', 'hotel'])],
            'allocation_state' => ['nullable', Rule::in(['allocated', 'partially_allocated', 'unallocated'])],
            'nationality' => ['nullable', 'string', 'max:100'],
            'airline' => ['nullable', 'string', 'max:3'],
            'flight_number' => ['nullable', 'string', 'max:10'],
            'city' => ['nullable', Rule::in(['Makkah', 'Madinah'])],
            'hotel' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', Rule::in(['company', 'self'])],
            'service_type' => ['nullable', 'string', 'max:40'],
            'cutoff_status' => ['nullable', Rule::in(['safe', 'approaching', 'overdue'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([25, 50, 100])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $validator->errors()->isEmpty()) {
                return;
            }

            if (Carbon::parse($this->string('start'))->diffInDays(Carbon::parse($this->string('end'))) > 366) {
                $validator->errors()->add('end', 'Interactive reports are limited to 366 days.');
            }
        });
    }
}
