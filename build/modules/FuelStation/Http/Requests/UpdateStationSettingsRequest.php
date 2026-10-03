<?php

namespace App\Modules\FuelStation\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\Inventory\Models\Item;
use App\Services\CurrentCompany;
use Illuminate\Validation\Rule;

class UpdateStationSettingsRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::COMPANY_UPDATE) && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return self::ruleSet();
    }

    public static function ruleSet(): array
    {
        $companyId = app(CurrentCompany::class)->get()->id;

        return [
            'month_end_stock_valuation' => ['sometimes', 'required', Rule::in(['inventory_cost', 'next_month_purchase_rate'])],
            'has_partners' => 'boolean',
            'has_amanat' => 'boolean',
            'has_lubricant_sales' => 'boolean',
            'has_investors' => 'boolean',
            'dual_meter_readings' => 'boolean',
            'payment_channels' => 'nullable|array',
            'payment_channels.*.code' => 'required|string',
            'payment_channels.*.label' => 'required|string',
            'payment_channels.*.type' => 'required|string|in:cash,bank_transfer,card_pos,fuel_card,mobile_wallet',
            'payment_channels.*.enabled' => 'boolean',
            'payment_channels.*.bank_account_id' => ['nullable', 'uuid', Rule::exists(Account::class, 'id')->where('company_id', $companyId)],
            'payment_channels.*.clearing_account_id' => ['nullable', 'uuid', Rule::exists(Account::class, 'id')->where('company_id', $companyId)],
            'payment_channels.*.settles_to' => ['nullable', 'string', 'in:clearing,bank,supplier'],
            'payment_channels.*.settles_to_vendor_id' => ['nullable', 'uuid', Rule::exists(Vendor::class, 'id')->where('company_id', $companyId)],
            // What the bank keeps of this channel's sales, e.g. 0.8 / 1 / 1.2 (% of the total).
            'payment_channels.*.fee_percent' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'cash_account_id' => ['nullable', 'uuid', Rule::exists(Account::class, 'id')->where('company_id', $companyId)],
            'fuel_sales_account_id' => ['nullable', 'uuid', Rule::exists(Account::class, 'id')->where('company_id', $companyId)],
            'fuel_cogs_account_id' => ['nullable', 'uuid', Rule::exists(Account::class, 'id')->where('company_id', $companyId)],
            'fuel_inventory_account_id' => ['nullable', 'uuid', Rule::exists(Account::class, 'id')->where('company_id', $companyId)],
            'cash_over_short_account_id' => ['nullable', 'uuid', Rule::exists(Account::class, 'id')->where('company_id', $companyId)],
            'partner_drawings_account_id' => ['nullable', 'uuid', Rule::exists(Account::class, 'id')->where('company_id', $companyId)],
            'employee_advances_account_id' => ['nullable', 'uuid', Rule::exists(Account::class, 'id')->where('company_id', $companyId)],
            'operating_bank_account_id' => ['nullable', 'uuid', Rule::exists(Account::class, 'id')->where('company_id', $companyId)],
            'fuel_card_clearing_account_id' => ['nullable', 'uuid', Rule::exists(Account::class, 'id')->where('company_id', $companyId)],
            'card_pos_clearing_account_id' => ['nullable', 'uuid', Rule::exists(Account::class, 'id')->where('company_id', $companyId)],
            'fuel_products' => 'nullable|array',
            'fuel_products.*.id' => ['required', 'uuid', Rule::exists(Item::class, 'id')->where('company_id', $companyId)],
            'fuel_products.*.income_account_id' => ['nullable', 'uuid', Rule::exists(Account::class, 'id')->where('company_id', $companyId)],
            'fuel_products.*.expense_account_id' => ['nullable', 'uuid', Rule::exists(Account::class, 'id')->where('company_id', $companyId)],
            'fuel_products.*.asset_account_id' => ['nullable', 'uuid', Rule::exists(Account::class, 'id')->where('company_id', $companyId)],
        ];
    }
}
