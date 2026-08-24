<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Accounting\Models\Account;
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
         * The model class, not the string 'acct.vendors'. Laravel splits a
         * dotted table on its FIRST dot into connection and table, and
         * config/database.php defines a connection called "acct" -- so
         * 'acct.vendors' was read as connection "acct", table "vendors", and the
         * check ran on a second Postgres session carrying neither this request's
         * RLS context nor its transaction. It saw no rows and passed every
         * duplicate through. Passing the model makes parseTable() read the table
         * and connection off it, which is the same pattern
         * StoreTicketBookingRequest already uses.
         */
        $vendorNumberRule = Rule::unique(Vendor::class, 'vendor_number')
            ->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at'));

        $emailRule = Rule::unique(Vendor::class, 'email')
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
                // Same reasoning as the unique rules above.
                Rule::exists(Account::class, 'id')->where(fn ($q) => $q
                    ->where('subtype', 'accounts_payable')
                    ->where('is_active', true)),
            ],
        ];
    }
}
