<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateTaxSettingsRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::TAX_SETTINGS_UPDATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'tax_enabled' => ['boolean'],
            'default_jurisdiction_id' => ['nullable', 'uuid', 'exists:tax.jurisdictions,id'],
            'default_sales_tax_rate_id' => ['nullable', 'uuid', 'exists:tax.tax_rates,id'],
            'default_purchase_tax_rate_id' => ['nullable', 'uuid', 'exists:tax.tax_rates,id'],
            'price_includes_tax' => ['boolean'],
            'rounding_mode' => ['nullable', Rule::in(['half_up', 'half_down', 'floor', 'ceiling', 'bankers'])],
            'rounding_precision' => ['nullable', 'integer', 'min:0', 'max:6'],
            'tax_number_label' => ['nullable', 'string', 'max:50'],
            'show_tax_column' => ['boolean'],
        ];
    }
}
