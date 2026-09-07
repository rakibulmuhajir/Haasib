<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\Driver;
use App\Modules\Umrah\Models\Hotel;
use App\Modules\Umrah\Models\HotelRoomRate;
use App\Modules\Umrah\Models\Passenger;
use App\Modules\Umrah\Models\TransportFare;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\VisaVendor;
use App\Services\CompanyContextService;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreQuickBookingRequest extends UmrahFormRequest
{
    public const SERVICE_MODES = [
        'visa',
        'visa_transport',
        'transport',
        'hotel',
        'visa_hotel',
        'transport_hotel',
        'complete',
    ];

    protected function permission(): string
    {
        return Permissions::UMRAH_GROUP_CREATE;
    }

    protected function prepareForValidation(): void
    {
        $mode = (string) $this->input('service_mode');
        $includesVisa = in_array($mode, ['visa', 'visa_transport', 'visa_hotel', 'complete'], true);
        $includesHotel = in_array($mode, ['hotel', 'visa_hotel', 'transport_hotel', 'complete'], true);
        $includesTransport = in_array($mode, ['visa_transport', 'transport', 'transport_hotel', 'complete'], true);

        $this->merge([
            'includes_visa' => $includesVisa,
            'includes_hotel' => $includesHotel,
            'transport_mode' => $includesTransport
                ? $this->input('transport_mode', VisaGroup::TRANSPORT_STANDARD_BUS)
                : VisaGroup::TRANSPORT_NONE,
            'transport_required' => $includesTransport,
        ]);

        $this->deriveGroupServiceFields();
    }

    public function rules(): array
    {
        return [
            'service_mode' => ['required', Rule::in(self::SERVICE_MODES)],
            'next_step' => ['required', Rule::in(['group', 'voucher'])],
            'idempotency_key' => ['required', 'uuid'],
            'group_number' => [
                'nullable',
                'string',
                'max:50',
                $this->uniqueBookingNumberForRequest(),
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'agent_id' => ['required', 'uuid', $this->activeForCompany(Agent::class, 'Selected agent was not found.')],
            'travel_date' => ['nullable', 'date'],
            'passenger_count' => ['required', 'integer', 'min:1', 'max:500'],
            'includes_visa' => ['required', 'boolean'],
            'includes_hotel' => ['required', 'boolean'],
            'transport_required' => ['required', 'boolean'],
            'transport_mode' => [
                'required',
                Rule::in(array_keys(VisaGroup::TRANSPORT_MODES)),
                $this->transportSellsSomethingRule(),
            ],
            'hotel_makkah_id' => ['nullable', 'uuid', $this->activeForCompany(Hotel::class, 'Selected Makkah hotel was not found.')],
            'hotel_madinah_id' => ['nullable', 'uuid', $this->activeForCompany(Hotel::class, 'Selected Madinah hotel was not found.')],
            'room_type' => ['nullable', Rule::in(array_keys(HotelRoomRate::TYPES))],
            'makkah_nights' => ['nullable', 'integer', 'min:0', 'max:90'],
            'madinah_nights' => ['nullable', 'integer', 'min:0', 'max:90'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'passengers' => ['nullable', 'array', 'max:500'],
            'passengers.*.full_name' => ['nullable', 'string', 'max:255'],
            'passengers.*.passport_number' => ['nullable', 'string', 'max:100'],
            'passengers.*.nationality' => ['nullable', Rule::in(array_keys(Agent::COUNTRIES))],
            'passengers.*.date_of_birth' => ['nullable', 'date'],
            'passengers.*.imported_age' => ['nullable', 'integer', 'min:0', 'max:130'],
            'passengers.*.service_type' => ['required', Rule::in(array_keys(Passenger::SERVICE_TYPES))],
            'passengers.*.transport_charge_amount' => ['required', 'numeric', 'in:0'],
            'passengers.*.visa_status' => ['nullable', Rule::in(array_keys(Passenger::STATUSES))],
            'transport_items' => [
                'nullable',
                'array',
                Rule::when($this->input('transport_mode') === VisaGroup::TRANSPORT_SPECIALIZED, ['required', 'min:1']),
            ],
            'transport_items.*.transport_fare_id' => ['required', 'uuid', 'distinct', $this->activeForCompany(TransportFare::class, 'Selected transport fare was not found.')],
            'transport_items.*.driver_id' => ['nullable', 'uuid', $this->activeForCompany(Driver::class, 'Selected driver was not found.')],
            'transport_items.*.scheduled_at' => ['nullable', 'date'],
            'transport_items.*.terminal' => ['required', Rule::in(['standard', 'hajj'])],
            'transport_items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'transport_items.*.passenger_count' => ['nullable', 'integer', 'min:1', 'max:500'],
            'transport_items.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $companyId = app(CompanyContextService::class)->getCompanyId();
                $visaVendor = VisaVendor::where('company_id', $companyId)
                    ->where('is_active', true)
                    ->where('is_default', true)
                    ->where('service_type', '!=', VisaVendor::SERVICE_TRANSPORT_PROVIDER)
                    ->withCompleteVisaRates()
                    ->first();

                if ($this->boolean('includes_visa') && ! $visaVendor) {
                    $validator->errors()->add('service_mode', 'Set an active default visa rate before selling visa service.');
                }

                if ($this->input('transport_mode') === VisaGroup::TRANSPORT_STANDARD_BUS) {
                    $providerId = $visaVendor?->resolvedMandatoryTransportVendorId()
                        ?: VisaVendor::where('company_id', $companyId)
                            ->where('is_active', true)
                            ->where('service_type', VisaVendor::SERVICE_TRANSPORT_PROVIDER)
                            ->orderBy('created_at')
                            ->value('id');
                    $providerExists = $providerId && VisaVendor::where('company_id', $companyId)
                        ->where('is_active', true)
                        ->where('service_type', VisaVendor::SERVICE_TRANSPORT_PROVIDER)
                        ->whereKey($providerId)
                        ->exists();

                    if (! $providerExists) {
                        $validator->errors()->add('transport_mode', 'Set an active standard transport rate before selling standard transport.');
                    }
                }

                $role = DB::table('auth.company_user')
                    ->where('company_id', $companyId)
                    ->where('user_id', $this->user()?->id)
                    ->where('is_active', true)
                    ->value('role');

                if ($role === 'agent') {
                    $agent = Agent::where('company_id', $companyId)
                        ->where('user_id', $this->user()?->id)
                        ->where('is_active', true)
                        ->first();

                    if (! $agent || $agent->id !== $this->input('agent_id')) {
                        $validator->errors()->add('agent_id', 'You can create bookings only for your own agent account.');
                    }

                    if ($this->input('next_step') === 'voucher' && ! $agent?->can_create_voucher) {
                        $validator->errors()->add('next_step', 'Your agent login cannot create vouchers. Save the booking instead.');
                    }
                } elseif ($this->input('next_step') === 'voucher'
                    && ! $this->user()?->hasCompanyPermission(Permissions::UMRAH_VOUCHER_CREATE)) {
                    $validator->errors()->add('next_step', 'You do not have permission to create vouchers.');
                }

                $namedPassengers = collect($this->input('passengers', []))
                    ->filter(fn ($passenger) => is_array($passenger) && filled($passenger['full_name'] ?? null))
                    ->count();

                if ($this->input('next_step') === 'voucher' && $namedPassengers === 0) {
                    $validator->errors()->add('passengers', 'Add at least one passenger before building the voucher.');
                }
            },
        ];
    }

    private function uniqueBookingNumberForRequest(): Closure
    {
        $companyId = app(CompanyContextService::class)->getCompanyId();
        $idempotencyKey = (string) $this->input('idempotency_key');

        return function (string $attribute, mixed $value, Closure $fail) use ($companyId, $idempotencyKey): void {
            if ($value === null || $value === '') {
                return;
            }

            $alreadyUsed = VisaGroup::query()
                ->where('company_id', $companyId)
                ->where('group_number', $value)
                ->whereNull('deleted_at')
                ->when($idempotencyKey !== '', fn ($query) => $query->where(function ($query) use ($idempotencyKey) {
                    $query->whereNull('idempotency_key')
                        ->orWhere('idempotency_key', '!=', $idempotencyKey);
                }))
                ->exists();

            if ($alreadyUsed) {
                $fail('This booking number is already used.');
            }
        };
    }
}
