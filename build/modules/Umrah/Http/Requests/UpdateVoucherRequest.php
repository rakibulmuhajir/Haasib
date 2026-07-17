<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\Hotel;
use App\Modules\Umrah\Models\HotelRoomRate;
use App\Modules\Umrah\Models\Voucher;
use App\Modules\Umrah\Services\TravelAccessService;
use App\Services\CompanyContextService;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateVoucherRequest extends UmrahFormRequest
{
    protected function permission(): string
    {
        return Permissions::UMRAH_VOUCHER_UPDATE;
    }

    public function authorize(): bool
    {
        if (! parent::authorize()) {
            return false;
        }
        $companyId = app(CompanyContextService::class)->getCompanyId();
        $voucher = Voucher::where('company_id', $companyId)->find($this->route('voucher'));
        if (! $voucher || $voucher->status !== Voucher::STATUS_DRAFT) {
            return false;
        }
        $access = app(TravelAccessService::class);
        if (! $access->isAgentMember($companyId, $this->user())) {
            return true;
        }

        return $access->agentCanEditVoucher($companyId, $this->user(), $voucher);
    }

    public function rules(): array
    {
        $requiresFlights = $this->input('service_bundle') !== Voucher::SERVICE_HOTEL;
        $companyId = app(CompanyContextService::class)->getCompanyId();
        $voucher = Voucher::where('company_id', $companyId)->find($this->route('voucher'));
        $access = app(TravelAccessService::class);
        $requiresReason = $voucher
            && ! $access->isAgentMember($companyId, $this->user())
            && $access->voucherHasStarted($voucher);

        return [
            'title' => ['required', 'string', 'max:255'],
            'service_bundle' => ['required', Rule::in(array_keys(Voucher::SERVICE_BUNDLES))],
            'onward_airline' => [Rule::requiredIf($requiresFlights), 'nullable', Rule::in(array_keys(Voucher::AIRLINES))],
            'onward_flight_number' => ['nullable', 'string', 'max:5', 'regex:/^[A-Za-z0-9]+$/'],
            'onward_departure_city' => [Rule::requiredIf($requiresFlights), 'nullable', Rule::in(array_keys(Voucher::AIRPORT_CITIES))],
            'onward_arrival_city' => [Rule::requiredIf($requiresFlights), 'nullable', Rule::in(array_keys(Voucher::AIRPORT_CITIES))],
            'onward_departure_at' => [Rule::requiredIf($requiresFlights), 'nullable', 'date'],
            'onward_arrival_at' => [Rule::requiredIf($requiresFlights), 'nullable', 'date', 'after:onward_departure_at'],
            'return_airline' => [Rule::requiredIf($requiresFlights), 'nullable', Rule::in(array_keys(Voucher::AIRLINES))],
            'return_flight_number' => ['nullable', 'string', 'max:5', 'regex:/^[A-Za-z0-9]+$/'],
            'return_departure_city' => [Rule::requiredIf($requiresFlights), 'nullable', Rule::in(array_keys(Voucher::AIRPORT_CITIES))],
            'return_arrival_city' => [Rule::requiredIf($requiresFlights), 'nullable', Rule::in(array_keys(Voucher::AIRPORT_CITIES))],
            'return_departure_at' => [Rule::requiredIf($requiresFlights), 'nullable', 'date', 'after:onward_arrival_at'],
            'return_arrival_at' => [Rule::requiredIf($requiresFlights), 'nullable', 'date', 'after:return_departure_at'],
            'hotel_stays' => ['required', 'array', 'min:1'],
            'hotel_stays.*.hotel_name' => ['required', 'string', 'max:255'],
            'hotel_stays.*.city' => ['required', 'string', 'max:100'],
            'hotel_stays.*.source' => ['required', Rule::in(['company', 'self'])],
            'hotel_stays.*.hotel_id' => ['nullable', 'required_if:hotel_stays.*.source,company', 'uuid', $this->existsForCompany(Hotel::class, 'Selected hotel was not found.')],
            'hotel_stays.*.room_type' => ['required', Rule::in(array_keys(HotelRoomRate::TYPES))],
            'hotel_stays.*.room_count' => ['required', 'integer', 'min:1', 'max:100'],
            'hotel_stays.*.check_in_date' => ['required', 'date_format:Y-m-d'],
            'hotel_stays.*.check_out_date' => ['required', 'date_format:Y-m-d'],
            'hotel_stays.*.notes' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string'],
            'override_reason' => [Rule::requiredIf($requiresReason), 'nullable', 'string', 'min:5', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $previousCheckout = null;
            $hotelOnly = $this->input('service_bundle') === Voucher::SERVICE_HOTEL;
            $windowStart = $hotelOnly ? null : Carbon::parse($this->input('onward_arrival_at'))->startOfDay();
            $windowEnd = $hotelOnly ? null : Carbon::parse($this->input('return_departure_at'))->startOfDay();
            foreach ($this->input('hotel_stays', []) as $index => $stay) {
                $checkIn = Carbon::parse($stay['check_in_date'])->startOfDay();
                $checkOut = Carbon::parse($stay['check_out_date'])->startOfDay();
                if ($checkOut->lte($checkIn)) {
                    $validator->errors()->add("hotel_stays.{$index}.check_out_date", 'Checkout must be after check-in.');
                }
                if (! $hotelOnly && ($checkIn->lt($windowStart) || $checkOut->gt($windowEnd))) {
                    $validator->errors()->add("hotel_stays.{$index}.check_in_date", 'Stay dates must be within the onward arrival and return departure dates.');
                }
                if ($previousCheckout && $checkIn->lt($previousCheckout)) {
                    $validator->errors()->add("hotel_stays.{$index}.check_in_date", 'This stay cannot start before the previous stay ends.');
                }
                $previousCheckout = $checkOut;
            }
            $companyId = app(CompanyContextService::class)->getCompanyId();
            $agent = Agent::where('company_id', $companyId)->where('user_id', $this->user()?->id)->where('is_active', true)->first();
            $deadlineField = $hotelOnly ? 'hotel_stays.0.check_in_date' : 'onward_departure_at';
            $deadlineValue = $hotelOnly ? $this->input('hotel_stays.0.check_in_date') : $this->input('onward_departure_at');
            if ($agent && Carbon::parse($deadlineValue)->lt(now()->addHours($agent->voucher_cutoff_hours))) {
                $validator->errors()->add($deadlineField, "Schedule changes must be saved at least {$agent->voucher_cutoff_hours} hours before service starts.");
            }
        }];
    }
}
