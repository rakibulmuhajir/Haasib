<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Accounting\Models\Vendor;
use App\Services\CompanyContextService;
use Illuminate\Validation\Rule;

class StoreVendorRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::VENDOR_CREATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        $companyId = app(CompanyContextService::class)->getCompanyId();
        $companyBaseCurrency = app(CompanyContextService::class)->getCompany()?->base_currency;

        /*
         * The table has to name the connection as well as the schema. Laravel
         * splits a dotted table on its FIRST dot into connection and table, and
         * config/database.php happens to define a connection called "acct" -- so
         * "acct.vendors" was read as connection "acct", table "vendors", and the
         * check ran on a second Postgres session. That session carries neither
         * the request's RLS context nor, under test, the open transaction, so it
         * saw no rows and the rule passed on every duplicate. Naming the default
         * connection first leaves "acct.vendors" as the table, which is what was
         * meant all along.
         */
        $vendors = config('database.default').'.acct.vendors';

        $vendorNumberRule = Rule::unique($vendors, 'vendor_number')
            ->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at'));

        $emailRule = Rule::unique($vendors, 'email')
            ->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at'));

        $baseCurrencyRule = $companyBaseCurrency
            ? Rule::in([$companyBaseCurrency])
            : 'string';

        return [
            'vendor_number' => ['nullable', 'string', 'max:50', $vendorNumberRule],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', $emailRule],
            'phone' => ['nullable', 'string', 'max:50'],
            'vendor_type' => ['nullable', Rule::in(array_keys(Vendor::TYPES))],
            'address.street' => ['nullable', 'string', 'max:255'],
            'address.city' => ['nullable', 'string', 'max:100'],
            'address.state' => ['nullable', 'string', 'max:100'],
            'address.zip' => ['nullable', 'string', 'max:20'],
            'address.country' => ['nullable', 'string', 'size:2'],
            'tax_id' => ['nullable', 'string', 'max:100'],
            'base_currency' => ['nullable', 'string', 'size:3', 'uppercase'],
            'payment_terms' => ['nullable', 'integer', 'min:0', 'max:365'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'website' => ['nullable', 'string', 'max:500'],
            'logo_url' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
            'ap_account_id' => [
                'nullable',
                'uuid',
                Rule::exists(config('database.default').'.acct.accounts', 'id')->where(fn ($q) => $q
                    ->where('subtype', 'accounts_payable')
                    ->where('is_active', true)),
            ],
        ];
    }
}
