<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\Hotel;
use App\Modules\Umrah\Models\HotelRoomRate;
use App\Modules\Umrah\Models\Passenger;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\Voucher;
use App\Modules\Umrah\Models\VoucherPassenger;
use App\Services\CompanyContextService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreVoucherRequest extends UmrahFormRequest
{
    public function authorize(): bool
    {
        if (! parent::authorize()) {
            return false;
        }
        $companyId = app(CompanyContextService::class)->getCompanyId();
        $role = DB::table('auth.company_user')->where('company_id', $companyId)->where('user_id', $this->user()?->id)->where('is_active', true)->value('role');
        if ($role !== 'agent') {
            return true;
        }

        return Agent::where('company_id', $companyId)->where('user_id', $this->user()?->id)->where('is_active', true)->where('can_create_voucher', true)->exists();
    }

    protected function permission(): string
    {
        return Permissions::UMRAH_VOUCHER_CREATE;
    }

    public function rules(): array
    {
        $isDraft = $this->input('status', Voucher::STATUS_DRAFT) === Voucher::STATUS_DRAFT;
        $requiresFlights = ! $isDraft && $this->input('service_bundle') !== Voucher::SERVICE_HOTEL;
        $requiresCompleteStay = ! $isDraft;
        $hotelIdRules = ['nullable'];
        if ($requiresCompleteStay) {
            $hotelIdRules[] = 'required_if:hotel_stays.*.source,company';
        }
        array_push($hotelIdRules, 'uuid', $this->existsForCompany(Hotel::class, 'Selected hotel was not found.'));

        return [
            'voucher_number' => [
                'nullable',
                'string',
                'max:50',
                $this->uniqueForCompany(Voucher::class, 'voucher_number', 'This voucher number is already used.'),
            ],
            'visa_group_id' => ['required', 'uuid', $this->existsForCompany(VisaGroup::class, 'Selected group was not found.')],
            'title' => ['required', 'string', 'max:255'],
            'service_bundle' => ['required', Rule::in(array_keys(Voucher::SERVICE_BUNDLES))],
            'status' => ['nullable', Rule::in([Voucher::STATUS_DRAFT, Voucher::STATUS_APPROVED])],
            'passenger_ids' => ['required', 'array', 'min:1'],
            'passenger_ids.*' => ['required', 'uuid'],
            'passenger_services' => ['required', 'array'],
            'passenger_services.*' => ['required', Rule::in(['visa_transport', 'transport_only'])],
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
            'hotel_stays.*.hotel_name' => [Rule::requiredIf($requiresCompleteStay), 'nullable', 'string', 'max:255'],
            'hotel_stays.*.city' => [Rule::requiredIf($requiresCompleteStay), 'nullable', 'string', 'max:100'],
            'hotel_stays.*.source' => [Rule::requiredIf($requiresCompleteStay), 'nullable', Rule::in(['company', 'self'])],
            'hotel_stays.*.hotel_id' => $hotelIdRules,
            'hotel_stays.*.room_type' => [Rule::requiredIf($requiresCompleteStay), 'nullable', Rule::in(array_keys(HotelRoomRate::TYPES))],
            'hotel_stays.*.room_count' => [Rule::requiredIf($requiresCompleteStay), 'nullable', 'integer', 'min:1', 'max:100'],
            'hotel_stays.*.check_in_date' => [Rule::requiredIf($requiresCompleteStay), 'nullable', 'date_format:Y-m-d'],
            'hotel_stays.*.check_out_date' => [Rule::requiredIf($requiresCompleteStay), 'nullable', 'date_format:Y-m-d'],
            'hotel_stays.*.notes' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $hasCompleteItinerary = $this->hasCompleteItinerary();
                $companyId = app(CompanyContextService::class)->getCompanyId();
                $groupId = (string) $this->input('visa_group_id');
                $group = VisaGroup::where('company_id', $companyId)->find($groupId);

                if ($group && ! array_key_exists($this->input('service_bundle'), Voucher::bundlesForTransportMode($group->transport_mode))) {
                    $validator->errors()->add('service_bundle', $group->transport_mode === 'none'
                        ? 'This group has self-arranged transport, so a transport bundle cannot be sold on it.'
                        : 'Selected service bundle is not valid for this group.');
                }

                $passengerIds = array_values(array_unique($this->input('passenger_ids', [])));
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
                    $agentId = $agent?->id;

                    $canAccessGroup = $agentId && VisaGroup::where('company_id', $companyId)
                        ->whereKey($groupId)
                        ->where('agent_id', $agentId)
                        ->exists();

                    if (! $canAccessGroup) {
                        $validator->errors()->add('visa_group_id', 'Selected group is not assigned to your agent login.');
                    }

                    if ($hasCompleteItinerary) {
                        $deadlineField = $this->input('service_bundle') === Voucher::SERVICE_HOTEL ? 'hotel_stays.0.check_in_date' : 'onward_departure_at';
                        $deadlineValue = $this->input('service_bundle') === Voucher::SERVICE_HOTEL
                            ? $this->input('hotel_stays.0.check_in_date')
                            : $this->input('onward_departure_at');
                        if ($agent && Carbon::parse($deadlineValue)->lt(now()->addHours($agent->voucher_cutoff_hours))) {
                            $validator->errors()->add($deadlineField, "Voucher must be created at least {$agent->voucher_cutoff_hours} hours before service starts.");
                        }
                    }
                }

                $validPassengerCount = Passenger::where('company_id', $companyId)
                    ->where('visa_group_id', $groupId)
                    ->whereIn('id', $passengerIds)
                    ->count();

                if ($validPassengerCount !== count($passengerIds)) {
                    $validator->errors()->add('passenger_ids', 'One or more selected passengers do not belong to this group.');
                }

                $alreadyAssigned = VoucherPassenger::where('company_id', $companyId)
                    ->where('visa_group_id', $groupId)
                    ->whereIn('passenger_id', $passengerIds)
                    ->exists();

                if ($alreadyAssigned) {
                    $validator->errors()->add('passenger_ids', 'One or more selected passengers already have a voucher.');
                }

                if (! $hasCompleteItinerary) {
                    return;
                }

                $hotelOnly = $this->input('service_bundle') === Voucher::SERVICE_HOTEL;
                $stayWindowStart = $hotelOnly ? null : Carbon::parse($this->input('onward_arrival_at'))->startOfDay();
                $stayWindowEnd = $hotelOnly ? null : Carbon::parse($this->input('return_departure_at'))->startOfDay();
                $previousCheckout = null;

                foreach ($this->input('hotel_stays', []) as $index => $stay) {
                    $checkIn = Carbon::parse($stay['check_in_date'])->startOfDay();
                    $checkOut = Carbon::parse($stay['check_out_date'])->startOfDay();

                    if ($checkOut->lte($checkIn)) {
                        $validator->errors()->add("hotel_stays.{$index}.check_out_date", 'Checkout must be after check-in.');
                    }

                    if (! $hotelOnly && ($checkIn->lt($stayWindowStart) || $checkOut->gt($stayWindowEnd))) {
                        $validator->errors()->add("hotel_stays.{$index}.check_in_date", 'Stay dates must be within the onward arrival and return departure dates.');
                    }

                    if ($previousCheckout && $checkIn->lt($previousCheckout)) {
                        $validator->errors()->add("hotel_stays.{$index}.check_in_date", 'This stay cannot start before the previous stay ends.');
                    }

                    $previousCheckout = $checkOut;
                }
            },
        ];
    }

    private function hasCompleteItinerary(): bool
    {
        if ($this->input('service_bundle') !== Voucher::SERVICE_HOTEL) {
            foreach (['onward_airline', 'onward_departure_city', 'onward_arrival_city', 'onward_departure_at', 'onward_arrival_at', 'return_airline', 'return_departure_city', 'return_arrival_city', 'return_departure_at', 'return_arrival_at'] as $field) {
                if (blank($this->input($field))) {
                    return false;
                }
            }
        }

        $stays = $this->input('hotel_stays', []);
        if ($stays === []) {
            return false;
        }

        return collect($stays)->every(function (array $stay): bool {
            foreach (['hotel_name', 'city', 'source', 'room_type', 'room_count', 'check_in_date', 'check_out_date'] as $field) {
                if (blank($stay[$field] ?? null)) {
                    return false;
                }
            }

            return ($stay['source'] ?? null) !== 'company' || filled($stay['hotel_id'] ?? null);
        });
    }
}
