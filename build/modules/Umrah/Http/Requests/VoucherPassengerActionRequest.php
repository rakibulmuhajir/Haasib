<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Modules\Umrah\Models\Voucher;
use App\Modules\Umrah\Services\TravelAccessService;
use App\Services\CompanyContextService;
use Illuminate\Validation\Rule;

abstract class VoucherPassengerActionRequest extends UmrahFormRequest
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
        if (! $voucher) {
            return false;
        }

        $access = app(TravelAccessService::class);
        if (! $access->isAgentMember($companyId, $this->user())) {
            return true;
        }

        return $voucher->status === Voucher::STATUS_DRAFT
            && $access->agentCanModifyVoucherNow($companyId, $this->user(), $voucher);
    }

    protected function commonRules(): array
    {
        $companyId = app(CompanyContextService::class)->getCompanyId();
        $source = Voucher::where('company_id', $companyId)->find($this->route('voucher'));
        $access = app(TravelAccessService::class);
        $requiresReason = $source
            && ! $access->isAgentMember($companyId, $this->user())
            && $access->voucherHasStarted($source);

        return [
            'passenger_ids' => ['required', 'array', 'min:1'],
            'passenger_ids.*' => ['required', 'uuid', 'distinct'],
            'override_reason' => [Rule::requiredIf($requiresReason), 'nullable', 'string', 'min:5', 'max:1000'],
        ];
    }
}
