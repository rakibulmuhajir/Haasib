<?php

namespace App\Http\Requests;

use App\Constants\Permissions;

/** Share a month's profit between the partners (preview or post). */
class ShareProfitRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::JOURNAL_CREATE) && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return static::ruleSet();
    }

    public static function ruleSet(): array
    {
        return [
            'month' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'dry_run' => ['nullable', 'boolean'],
        ];
    }
}
