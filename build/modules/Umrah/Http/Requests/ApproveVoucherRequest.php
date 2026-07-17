<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Modules\Umrah\Models\Voucher;
use App\Modules\Umrah\Services\TravelAccessService;
use App\Services\CompanyContextService;
use Illuminate\Validation\Rule;

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
}
