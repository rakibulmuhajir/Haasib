<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Modules\Umrah\Models\Voucher;
use App\Modules\Umrah\Services\TravelAccessService;
use App\Services\CompanyContextService;
use Illuminate\Validation\Rule;

class DeleteVoucherRequest extends UmrahFormRequest
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
        $access = app(TravelAccessService::class);
        if (! $voucher || $voucher->status !== Voucher::STATUS_DRAFT) {
            return false;
        }

        return ! $access->isAgentMember($companyId, $this->user()) || $access->agentCanEditVoucher($companyId, $this->user(), $voucher);
    }

    public function rules(): array
    {
        $companyId = app(CompanyContextService::class)->getCompanyId();
        $voucher = Voucher::where('company_id', $companyId)->find($this->route('voucher'));
        $access = app(TravelAccessService::class);
        $required = $voucher && ! $access->isAgentMember($companyId, $this->user()) && $access->voucherHasStarted($voucher);

        return ['reason' => [Rule::requiredIf($required), 'nullable', 'string', 'min:5', 'max:1000']];
    }
}
