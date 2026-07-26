<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Modules\Umrah\Models\Voucher;
use App\Modules\Umrah\Services\TravelAccessService;
use App\Services\CompanyContextService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ApproveVoucherRequest extends UmrahFormRequest
{
    public function authorize(): bool
    {
        if (! parent::authorize()) {
            return false;
        }
        $companyId = app(CompanyContextService::class)->getCompanyId();
        $voucher = Voucher::where('company_id', $companyId)->find($this->route('voucher'));
        if (! $voucher) {
            return false;
        }
        $access = app(TravelAccessService::class);
        if (! $access->isAgentMember($companyId, $this->user())) {
            return true;
        }
        $agent = $access->linkedAgent($companyId, $this->user());

        return $agent && $agent->can_approve_voucher && $voucher->agent_id === $agent->id && ! $access->voucherHasStarted($voucher);
    }

    protected function permission(): string
    {
        return Permissions::UMRAH_VOUCHER_APPROVE;
    }

    public function rules(): array
    {
        $companyId = app(CompanyContextService::class)->getCompanyId();
        $voucher = Voucher::where('company_id', $companyId)->find($this->route('voucher'));
        $access = app(TravelAccessService::class);
        $requiresReason = $voucher && ! $access->isAgentMember($companyId, $this->user()) && $access->voucherHasStarted($voucher);

        return ['override_reason' => [Rule::requiredIf($requiresReason), 'nullable', 'string', 'min:5', 'max:1000']];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $companyId = app(CompanyContextService::class)->getCompanyId();
            $voucher = Voucher::where('company_id', $companyId)->find($this->route('voucher'));
            if (! $voucher || ! $this->hasCompleteItinerary($voucher)) {
                $validator->errors()->add('voucher', 'Complete the flight and hotel itinerary before approving this voucher.');
            }
        }];
    }

    private function hasCompleteItinerary(Voucher $voucher): bool
    {
        if ($voucher->service_bundle !== Voucher::SERVICE_HOTEL) {
            foreach (['onward_airline', 'onward_departure_city', 'onward_arrival_city', 'onward_departure_at', 'onward_arrival_at', 'return_airline', 'return_departure_city', 'return_arrival_city', 'return_departure_at', 'return_arrival_at'] as $field) {
                if (blank($voucher->{$field})) {
                    return false;
                }
            }
        }

        $stays = $voucher->hotel_stays ?? [];

        return $stays !== [] && collect($stays)->every(function (array $stay): bool {
            foreach (['hotel_name', 'city', 'source', 'room_type', 'room_count', 'check_in_date', 'check_out_date'] as $field) {
                if (blank($stay[$field] ?? null)) {
                    return false;
                }
            }

            return ($stay['source'] ?? null) !== 'company' || filled($stay['hotel_id'] ?? null);
        });
    }
}
