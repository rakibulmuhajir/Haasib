<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Modules\Umrah\Models\Voucher;
use App\Modules\Umrah\Services\TravelAccessService;
use App\Services\CompanyContextService;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MoveVoucherPassengersRequest extends VoucherPassengerActionRequest
{
    public function rules(): array
    {
        $rules = $this->commonRules();
        $companyId = app(CompanyContextService::class)->getCompanyId();
        $targetId = $this->input('target_voucher_id');
        $target = is_string($targetId) && Str::isUuid($targetId)
            ? Voucher::where('company_id', $companyId)->find($targetId) : null;
        $source = Voucher::where('company_id', $companyId)->find($this->route('voucher'));
        $access = app(TravelAccessService::class);
        if (! $access->isAgentMember($companyId, $this->user()) && (
            $source?->status === Voucher::STATUS_APPROVED || $target?->status === Voucher::STATUS_APPROVED
            || ($target && $access->voucherHasStarted($target)))) {
            $rules['override_reason'] = [Rule::requiredIf(true), 'nullable', 'string', 'min:5', 'max:1000'];
        }
        $rules['target_voucher_id'] = ['bail', 'required', 'uuid', $this->existsForCompany(Voucher::class, 'Selected destination voucher was not found.')];

        return $rules;
    }
}
