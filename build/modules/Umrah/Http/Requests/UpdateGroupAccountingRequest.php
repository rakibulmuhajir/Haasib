<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\VisaVendor;
use App\Services\CompanyContextService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateGroupAccountingRequest extends UmrahFormRequest
{
    protected function permission(): string
    {
        return Permissions::UMRAH_GROUP_ACCOUNTING_UPDATE;
    }

    public function rules(): array
    {
        $companyId = app(CompanyContextService::class)->getCompanyId();

        return [
            'vendor_id' => ['required', 'uuid', Rule::exists(VisaVendor::class, 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('vendor_type', '!=', VisaVendor::TYPE_TRANSPORT_PROVIDER)->where('is_active', true)->whereNull('deleted_at'))],
            'mandatory_transport_vendor_id' => ['nullable', 'uuid', Rule::exists(VisaVendor::class, 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('is_active', true)->whereNull('deleted_at')->where(fn ($vendor) => $vendor->where('vendor_type', VisaVendor::TYPE_TRANSPORT_PROVIDER)->orWhere('provides_mandatory_transport', true)))],
            'visa_sale_amount' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'transport_amount' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'discount_amount' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $companyId = app(CompanyContextService::class)->getCompanyId();
            $group = VisaGroup::where('company_id', $companyId)->find($this->route('group'));
            if (! $group) {
                return;
            }

            $gross = round((float) $this->input('visa_sale_amount') + (float) $this->input('transport_amount') + (float) $group->hotel_amount, 2);
            $receivable = max(round($gross - (float) $this->input('discount_amount'), 2), 0);
            if ((float) $this->input('discount_amount') > $gross) {
                $validator->errors()->add('discount_amount', 'Discount cannot exceed total group charges.');
            }
            if ($receivable + 0.01 < (float) $group->total_paid) {
                $validator->errors()->add('discount_amount', 'The adjusted total cannot be lower than the amount already received.');
            }
        }];
    }
}
