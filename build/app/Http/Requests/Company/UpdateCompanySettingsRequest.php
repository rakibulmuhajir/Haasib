<?php

namespace App\Http\Requests\Company;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class UpdateCompanySettingsRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::COMPANY_UPDATE) && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'logo_url' => ['sometimes', 'nullable', 'url:http,https', 'max:500'],
            'logo' => ['sometimes', 'nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            // Stamp and signature printed on final documents (1 MB; a transparent PNG works best).
            'stamp' => ['sometimes', 'nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'signature' => ['sometimes', 'nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'remove_stamp' => ['sometimes', 'boolean'],
            'remove_signature' => ['sometimes', 'boolean'],
            'signer_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'signer_title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'stamp_documents' => ['sometimes', 'array'],
            'stamp_documents.*' => ['boolean'],
            'language' => ['sometimes', 'string', 'max:10'],
            'locale' => ['sometimes', 'string', 'max:10'],
            'contact_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'website' => ['sometimes', 'nullable', 'url:http,https', 'max:500'],
            // Printed as "Billed by" on invoices from a statement.
            'billed_by_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'billed_by_designation' => ['sometimes', 'nullable', 'string', 'max:120'],
            'billed_by_phone' => ['sometimes', 'nullable', 'string', 'max:50'],

            // The postal address printed on every document this company issues.
            // Same shape as acct.customers.billing_address so one renderer
            // serves every party on a document rather than one per side.
            'address' => ['sometimes', 'nullable', 'array'],
            'address.line1' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address.line2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address.city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'address.state' => ['sometimes', 'nullable', 'string', 'max:120'],
            'address.postal_code' => ['sometimes', 'nullable', 'string', 'max:32'],
            'address.country' => ['sometimes', 'nullable', 'string', 'max:120'],
            'fiscal_year_start_month' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'auto_create_fiscal_year' => ['sometimes', 'boolean'],
            'default_period_type' => ['sometimes', 'string', 'in:monthly,quarterly,yearly'],
        ];
    }
}
