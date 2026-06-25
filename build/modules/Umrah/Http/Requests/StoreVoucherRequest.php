<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Modules\Umrah\Models\Agent;
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
    protected function permission(): string
    {
        return Permissions::UMRAH_VOUCHER_CREATE;
    }

    public function rules(): array
    {
        return [
            'voucher_number' => [
                'nullable',
                'string',
                'max:50',
                $this->uniqueForCompany(Voucher::class, 'voucher_number', 'This voucher number is already used.'),
            ],
            'visa_group_id' => ['required', 'uuid', $this->existsForCompany(VisaGroup::class, 'Selected group was not found.')],
            'title' => ['required', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(array_keys(Voucher::STATUSES))],
            'passenger_ids' => ['required', 'array', 'min:1'],
            'passenger_ids.*' => ['required', 'uuid'],
            'onward_airline' => ['required', Rule::in(array_keys(Voucher::AIRLINES))],
            'onward_flight_number' => ['nullable', 'string', 'max:80'],
            'onward_departure_city' => ['required', Rule::in(array_keys(Voucher::AIRPORT_CITIES))],
            'onward_arrival_city' => ['required', Rule::in(array_keys(Voucher::AIRPORT_CITIES))],
            'onward_departure_at' => ['required', 'date'],
            'onward_arrival_at' => ['required', 'date', 'after_or_equal:onward_departure_at'],
            'return_airline' => ['required', Rule::in(array_keys(Voucher::AIRLINES))],
            'return_flight_number' => ['nullable', 'string', 'max:80'],
            'return_departure_city' => ['required', Rule::in(array_keys(Voucher::AIRPORT_CITIES))],
            'return_arrival_city' => ['required', Rule::in(array_keys(Voucher::AIRPORT_CITIES))],
            'return_departure_at' => ['required', 'date', 'after_or_equal:onward_arrival_at'],
            'return_arrival_at' => ['required', 'date', 'after_or_equal:return_departure_at'],
            'hotel_stays' => ['nullable', 'array'],
            'hotel_stays.*.hotel_name' => ['required_with:hotel_stays', 'string', 'max:255'],
            'hotel_stays.*.city' => ['nullable', 'string', 'max:100'],
            'hotel_stays.*.check_in_date' => ['required_with:hotel_stays', 'date'],
            'hotel_stays.*.check_out_date' => ['required_with:hotel_stays', 'date'],
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

                $companyId = app(CompanyContextService::class)->getCompanyId();
                $groupId = (string) $this->input('visa_group_id');
                $passengerIds = array_values(array_unique($this->input('passenger_ids', [])));
                $role = DB::table('auth.company_user')
                    ->where('company_id', $companyId)
                    ->where('user_id', $this->user()?->id)
                    ->where('is_active', true)
                    ->value('role');

                if ($role === 'member') {
                    $agentId = Agent::where('company_id', $companyId)
                        ->where('user_id', $this->user()?->id)
                        ->where('is_active', true)
                        ->value('id');

                    $canAccessGroup = $agentId && VisaGroup::where('company_id', $companyId)
                        ->whereKey($groupId)
                        ->where('agent_id', $agentId)
                        ->exists();

                    if (! $canAccessGroup) {
                        $validator->errors()->add('visa_group_id', 'Selected group is not assigned to your agent login.');
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

                $journeyStart = Carbon::parse($this->input('onward_departure_at'))->startOfDay();
                $journeyEnd = Carbon::parse($this->input('return_arrival_at'))->endOfDay();

                foreach ($this->input('hotel_stays', []) as $index => $stay) {
                    $checkIn = Carbon::parse($stay['check_in_date']);
                    $checkOut = Carbon::parse($stay['check_out_date']);

                    if ($checkOut->lt($checkIn)) {
                        $validator->errors()->add("hotel_stays.{$index}.check_out_date", 'Checkout cannot be before check-in.');
                    }

                    if ($checkIn->lt($journeyStart) || $checkOut->gt($journeyEnd)) {
                        $validator->errors()->add("hotel_stays.{$index}.check_in_date", 'Hotel stay dates must be within the flight journey dates.');
                    }
                }
            },
        ];
    }
}
