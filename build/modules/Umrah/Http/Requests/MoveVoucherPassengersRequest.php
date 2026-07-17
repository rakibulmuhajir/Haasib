<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Modules\Umrah\Models\Voucher;
use App\Modules\Umrah\Services\TravelAccessService;
use App\Services\CompanyContextService;
use Illuminate\Validation\Rule;

class MoveVoucherPassengersRequest extends VoucherPassengerActionRequest
{
    public function rules(): array
    {
        $rules = $this->commonRules();
        $companyId = app(CompanyContextService::class)->getCompanyId();
        $target = Voucher::where('company_id', $companyId)->find($this->input('target_voucher_id'));
        $access = app(TravelAccessService::class);
        if ($target && ! $access->isAgentMember($companyId, $this->user()) && $access->voucherHasStarted($target)) {
            $rules['override_reason'] = [Rule::requiredIf(true), 'nullable', 'string', 'min:5', 'max:1000'];
        }
        $rules['target_voucher_id'] = ['required', 'uuid', $this->existsForCompany(Voucher::class, 'Selected destination voucher was not found.')];

        return $rules;
    }
}
