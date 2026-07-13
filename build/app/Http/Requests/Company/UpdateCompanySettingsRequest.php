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
            'language' => ['sometimes', 'string', 'max:10'],
            'locale' => ['sometimes', 'string', 'max:10'],
            'contact_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'website' => ['sometimes', 'nullable', 'url:http,https', 'max:500'],
            'fiscal_year_start_month' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'auto_create_fiscal_year' => ['sometimes', 'boolean'],
            'default_period_type' => ['sometimes', 'string', 'in:monthly,quarterly,yearly'],
        ];
    }
}
