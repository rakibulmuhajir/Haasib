<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\CommercialRate;
use App\Modules\Umrah\Models\HotelRoomRate;
use App\Modules\Umrah\Models\PricingCategory;
use App\Modules\Umrah\Models\TransportFare;
use App\Modules\Umrah\Models\VisaVendor;
use App\Services\CompanyContextService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveCommercialRateRequest extends UmrahFormRequest
{
    protected function permission(): string
    {
        return Permissions::UMRAH_PRICING_UPDATE;
    }

    protected function prepareForValidation(): void
    {
        $companyId = app(CompanyContextService::class)->getCompanyId();
        $this->merge([
            'currency' => $companyId
                ? \App\Models\Company::whereKey($companyId)->value('base_currency')
                : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'service_type' => ['required', Rule::in(array_keys(CommercialRate::SERVICE_TYPES))],
            'target_id' => ['required', 'uuid'],
            'scope_type' => ['required', Rule::in(array_keys(CommercialRate::SCOPE_TYPES))],
            'scope_id' => ['nullable', 'required_unless:scope_type,default', 'uuid'],
            'calculation_type' => ['required', Rule::in(array_keys(CommercialRate::CALCULATION_TYPES))],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'percentage' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'cost_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'currency' => ['required', 'string', 'size:3'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'effective_until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $companyId = app(CompanyContextService::class)->getCompanyId();
            $serviceType = $this->string('service_type')->toString();
            $scopeType = $this->string('scope_type')->toString();
            $calculationType = $this->string('calculation_type')->toString();
            $targetId = $this->string('target_id')->toString();
            $scopeId = $this->string('scope_id')->toString();

            if (! $this->targetExists($companyId, $serviceType, $targetId)) {
                $validator->errors()->add('target_id', 'The selected service is not available for this company.');
            }

            if ($scopeType === CommercialRate::SCOPE_DEFAULT && $scopeId !== '') {
                $validator->errors()->add('scope_id', 'Default rates do not use an agent or category.');
            }
            if ($scopeType === CommercialRate::SCOPE_CATEGORY && $scopeId !== ''
                && ! PricingCategory::where('company_id', $companyId)->where('is_active', true)->whereKey($scopeId)->exists()) {
                $validator->errors()->add('scope_id', 'Select an active pricing category.');
            }
            if ($scopeType === CommercialRate::SCOPE_AGENT && $scopeId !== ''
                && ! Agent::where('company_id', $companyId)->where('is_active', true)->whereKey($scopeId)->exists()) {
                $validator->errors()->add('scope_id', 'Select an active agent.');
            }
            if ($scopeType !== CommercialRate::SCOPE_DEFAULT && $scopeId === '') {
                $validator->errors()->add('scope_id', 'Select who this pricing rule applies to.');
            }
            if ($scopeType === CommercialRate::SCOPE_DEFAULT && $calculationType !== CommercialRate::CALC_SET_PRICE) {
                $validator->errors()->add('calculation_type', 'A default rate must set an exact price.');
            }
            if ($scopeType !== CommercialRate::SCOPE_DEFAULT && $this->filled('cost_amount')) {
                $validator->errors()->add('cost_amount', 'Supplier cost can be set only on a default rate.');
            }

            $amountTypes = [CommercialRate::CALC_SET_PRICE, CommercialRate::CALC_DISCOUNT_AMOUNT, CommercialRate::CALC_MARKUP_AMOUNT];
            if (in_array($calculationType, $amountTypes, true) && ! $this->filled('amount')) {
                $validator->errors()->add('amount', 'Enter the price or adjustment amount.');
            }
            if (! in_array($calculationType, $amountTypes, true) && ! $this->filled('percentage')) {
                $validator->errors()->add('percentage', 'Enter the percentage.');
            }
            if ($calculationType === CommercialRate::CALC_DISCOUNT_PERCENTAGE && (float) $this->input('percentage') > 100) {
                $validator->errors()->add('percentage', 'A discount cannot exceed 100%.');
            }

            if ($validator->errors()->isEmpty() && $this->overlapsExistingRate($companyId, $serviceType, $targetId, $scopeType, $scopeId)) {
                $validator->errors()->add('effective_from', 'This rule overlaps another active rate for the same service and customer scope.');
            }
        });
    }

    private function targetExists(string $companyId, string $serviceType, string $targetId): bool
    {
        return match ($serviceType) {
            CommercialRate::SERVICE_VISA_ADULT, CommercialRate::SERVICE_VISA_CHILD => VisaVendor::where('company_id', $companyId)
                ->where('service_type', '!=', VisaVendor::SERVICE_TRANSPORT_PROVIDER)->whereKey($targetId)->exists(),
            CommercialRate::SERVICE_STANDARD_TRANSPORT => VisaVendor::where('company_id', $companyId)
                ->where('service_type', VisaVendor::SERVICE_TRANSPORT_PROVIDER)->whereKey($targetId)->exists(),
            CommercialRate::SERVICE_TRANSPORT_FARE => TransportFare::where('company_id', $companyId)->whereKey($targetId)->exists(),
            CommercialRate::SERVICE_HOTEL_ROOM => HotelRoomRate::where('company_id', $companyId)->whereKey($targetId)->exists(),
            default => false,
        };
    }

    private function overlapsExistingRate(
        string $companyId,
        string $serviceType,
        string $targetId,
        string $scopeType,
        string $scopeId,
    ): bool {
        $targetColumn = match ($serviceType) {
            CommercialRate::SERVICE_TRANSPORT_FARE => 'transport_fare_id',
            CommercialRate::SERVICE_HOTEL_ROOM => 'hotel_room_rate_id',
            default => 'visa_vendor_id',
        };
        $query = CommercialRate::where('company_id', $companyId)
            ->where('service_type', $serviceType)
            ->where($targetColumn, $targetId)
            ->where('scope_type', $scopeType)
            ->where('is_active', true)
            ->when($this->route('rate'), fn (Builder $builder) => $builder->whereKeyNot($this->route('rate')))
            ->where(fn (Builder $builder) => $builder
                ->whereNull('effective_until')
                ->orWhereDate('effective_until', '>=', $this->input('effective_from')))
            ->where(fn (Builder $builder) => $builder
                ->whereDate('effective_from', '<=', $this->input('effective_until') ?: '9999-12-31'));

        if ($scopeType === CommercialRate::SCOPE_CATEGORY) {
            $query->where('pricing_category_id', $scopeId);
        } elseif ($scopeType === CommercialRate::SCOPE_AGENT) {
            $query->where('agent_id', $scopeId);
        }

        return $query->exists();
    }
}
